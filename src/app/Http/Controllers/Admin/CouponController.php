<?php

namespace App\Http\Controllers\Admin;

use App\Models\Coupon;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cupons de desconto dos eventos do organizador.
 *
 * Diferente dos outros cadastros do painel, este é uma tela só: a lista e o
 * formulário (num modal) moram juntas. Cupom é registro curto e de vida curta —
 * sair da lista para criar um e voltar para criar o próximo seria atrito à toa.
 * Por isso não existem as rotas `create` e `edit`.
 *
 * Três travas passam a valer depois do primeiro uso, e todas vivem AQUI, não só
 * na tela: apagar deixa de ser possível, e código e evento ficam congelados.
 * A tela desabilita o botão e trava o campo porque é o comportamento honesto; o
 * servidor recusa porque esconder no front não é proteger.
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
class CouponController extends AdminController
{
    public function index(Request $request)
    {
        $organizerId = $this->organizerId();

        $eventoFiltro = (int) $request->query('evento');

        // A busca é normalizada como o código: quem digita "corre10" na lupa
        // está procurando o CORRE10 que está no banco.
        $busca = Coupon::normalizarCodigo($request->query('busca'));

        $cupons = Coupon::doOrganizador($organizerId)
            ->with('event')
            ->join('events', 'events.id', '=', 'coupons.event_id')
            ->when($eventoFiltro, fn ($q) => $q->where('coupons.event_id', $eventoFiltro))
            ->when($busca !== '', fn ($q) => $q->where('coupons.code', 'like', "%{$busca}%"))
            ->orderByDesc('events.event_date')
            ->orderBy('coupons.code')
            ->select('coupons.*')
            ->paginate(20)
            ->withQueryString();

        return view('admin.coupons.index', [
            'cupons' => $cupons,
            // O filtro lista todos os eventos; o formulário, só os que ainda
            // podem receber cupom. São perguntas diferentes.
            'eventosDoFiltro' => Event::where('organizer_id', $organizerId)
                ->orderByDesc('event_date')
                ->get(['id', 'title', 'event_date']),
            'eventosParaCupom' => $this->eventosParaCupom(),
            'eventoFiltro' => $eventoFiltro,
            'busca' => (string) $request->query('busca'),
        ]);
    }

    public function store(Request $request)
    {
        $cupom = Coupon::create($this->validar($request));

        return redirect()
            ->route('admin.cupons.index')
            ->with('sucesso', "Cupom \"{$cupom->code}\" criado.");
    }

    public function update(Request $request, int $id)
    {
        $cupom = $this->cupomDoOrganizador($id);

        if ($erro = $this->recusarTrocaDeIdentidade($request, $cupom)) {
            return $erro;
        }

        $cupom->update($this->validar($request, $cupom));

        return redirect()
            ->route('admin.cupons.index')
            ->with('sucesso', "Cupom \"{$cupom->code}\" atualizado.");
    }

    public function destroy(int $id)
    {
        $cupom = $this->cupomDoOrganizador($id);

        // Cupom usado virou parte do histórico de quem pagou menos. Apagar
        // reescreveria o passado; desativar resolve o problema real (tirar de
        // circulação) sem perder o registro.
        if ($cupom->foiUsado()) {
            return back()->withErrors([
                'cupom' => "O cupom \"{$cupom->code}\" já foi usado {$this->vezes($cupom)} e por isso não pode ser apagado. Desative-o para tirá-lo de circulação sem perder o histórico.",
            ]);
        }

        $codigo = $cupom->code;
        $cupom->delete();

        return redirect()
            ->route('admin.cupons.index')
            ->with('sucesso', "Cupom \"{$codigo}\" apagado.");
    }

    /**
     * Liga e desliga o cupom.
     *
     * Enquanto não estiver encerrado, o organizador alterna à vontade — mesmo
     * com usos já registrados. Encerrado (esgotado ou vencido) é estado sem
     * volta, e a recusa vale nos dois sentidos: um cupom encerrado já lê como
     * inativo, então "desligar" não significaria nada.
     */
    public function status(int $id)
    {
        $cupom = $this->cupomDoOrganizador($id);

        if ($cupom->encerrado()) {
            $motivo = $cupom->esgotado()
                ? "já teve os {$cupom->total_quantity} usos gastos"
                : 'passou da data de validade em ' . $cupom->expires_at->format('d/m/Y');

            return back()->withErrors([
                'cupom' => "O cupom \"{$cupom->code}\" {$motivo} e não pode mais ser ligado nem desligado. Crie um cupom novo para retomar a promoção.",
            ]);
        }

        $cupom->active = ! $cupom->active;
        $cupom->save();

        return back()->with('sucesso', $cupom->active
            ? "Cupom \"{$cupom->code}\" ativado."
            : "Cupom \"{$cupom->code}\" desativado.");
    }

    /*
    |--------------------------------------------------------------------------
    | Apoio
    |--------------------------------------------------------------------------
    */

    /**
     * O cupom, já filtrado pelo organizador do usuário logado.
     *
     * 404 e não 403: um organizador não deve nem descobrir que o cupom de outro
     * existe. O filtro passa pelo evento — `coupons` não tem organizer_id.
     */
    private function cupomDoOrganizador(int $id): Coupon
    {
        return Coupon::where('id', $id)
            ->doOrganizador($this->organizerId())
            ->firstOrFail();
    }

    /**
     * Recusa, com mensagem explícita, a tentativa de trocar código ou evento de
     * um cupom que já foi usado.
     *
     * Podia simplesmente ignorar os campos, mas o silêncio aqui é pior: o
     * organizador sairia da tela achando que trocou o código, e só descobriria
     * quando o atleta reclamasse que o cupom do flyer não funciona.
     */
    private function recusarTrocaDeIdentidade(Request $request, Coupon $cupom)
    {
        if (! $cupom->foiUsado()) {
            return null;
        }

        $trocouCodigo = $request->has('code')
            && Coupon::normalizarCodigo($request->input('code')) !== $cupom->code;

        $trocouEvento = $request->has('event_id')
            && (int) $request->input('event_id') !== $cupom->event_id;

        if (! $trocouCodigo && ! $trocouEvento) {
            return null;
        }

        return back()->withInput()->withErrors([
            'code' => "O cupom \"{$cupom->code}\" já foi usado {$this->vezes($cupom)}: o código e o evento não podem mais ser trocados. O desconto, a quantidade, a validade e a descrição continuam editáveis.",
        ]);
    }

    private function validar(Request $request, ?Coupon $cupom = null): array
    {
        // Normaliza ANTES de validar: o regex e a checagem de repetição
        // precisam enxergar o valor que vai para o banco, não o que foi
        // digitado com espaço sobrando e em minúsculo.
        $request->merge(['code' => Coupon::normalizarCodigo($request->input('code'))]);

        $percentual = $request->input('discount_type') === Coupon::TIPO_PERCENTUAL;
        $travado = $cupom?->foiUsado() ?? false;

        // A quantidade total não pode cair abaixo do que já foi usado: a tela
        // mostraria "geradas 1 / utilizadas 3", que não quer dizer nada. Quem
        // quer encerrar o cupom desativa, não encolhe o limite.
        $minimoDaQuantidade = max(1, $cupom?->used_quantity ?? 0);

        $regras = [
            'description' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', Rule::in(Coupon::TIPOS)],
            'discount_value' => $percentual
                ? ['required', 'numeric', 'between:1,100']
                : ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'total_quantity' => ['required', 'integer', "min:{$minimoDaQuantidade}", 'max:100000'],
            'expires_at' => ['required', 'date'],
            'active' => ['boolean'],
        ];

        // Data no passado é recusada ao criar e ao mudar a data. Manter a que já
        // estava lá é permitido: senão não daria para corrigir a descrição de um
        // cupom vencido sem antes inventar uma validade nova para ele.
        if ($request->input('expires_at') !== $cupom?->expires_at?->toDateString()) {
            $regras['expires_at'][] = 'after_or_equal:today';
        }

        // Código e evento só entram enquanto o cupom não tiver uso nenhum.
        if (! $travado) {
            $regras['event_id'] = [
                'required',
                'integer',
                // Rule::in e não Rule::exists porque a lista permitida é
                // "eventos meus que ainda não aconteceram" MAIS o evento atual
                // do cupom — que pode já ter passado, e ainda assim precisa
                // continuar sendo uma escolha válida na edição.
                Rule::in($this->idsDeEventosPermitidos($cupom)),
            ];

            $regras['code'] = [
                'required',
                'string',
                'regex:/^[A-Z0-9]{6,7}$/',
                // Único por evento, não global (ver a migration).
                Rule::unique('coupons', 'code')
                    ->where('event_id', (int) $request->input('event_id'))
                    ->ignore($cupom?->id),
            ];
        }

        $dados = $request->validate($regras, $this->mensagens($cupom));

        $dados['active'] = $request->boolean('active');

        return $dados;
    }

    private function mensagens(?Coupon $cupom = null): array
    {
        $minimoDaQuantidade = $cupom?->foiUsado()
            ? "O cupom já foi usado {$this->vezes($cupom)}: a quantidade total não pode ficar abaixo disso. Para encerrá-lo, desative."
            : 'O cupom precisa permitir pelo menos um uso.';

        return [
            'event_id.required' => 'Escolha o evento em que este cupom vale.',
            'event_id.in' => 'Escolha um evento seu que ainda não aconteceu.',
            'code.required' => 'Informe o código do cupom.',
            'code.regex' => 'O código precisa ter 6 ou 7 caracteres, usando apenas letras de A a Z e números — sem espaços, acentos ou símbolos.',
            'code.unique' => 'Já existe um cupom com esse código neste evento.',
            'discount_type.required' => 'Escolha se o desconto é em porcentagem ou em reais.',
            'discount_type.in' => 'Escolha se o desconto é em porcentagem ou em reais.',
            'discount_value.required' => 'Informe o valor do desconto.',
            'discount_value.between' => 'O desconto em porcentagem precisa ficar entre 1% e 100%.',
            'discount_value.min' => 'O desconto em reais precisa ser de pelo menos R$ 0,01.',
            'discount_value.max' => 'O desconto em reais está alto demais.',
            'total_quantity.required' => 'Informe quantas vezes o cupom pode ser usado.',
            'total_quantity.min' => $minimoDaQuantidade,
            'expires_at.required' => 'Informe até quando o cupom vale.',
            'expires_at.date' => 'A data de expiração não é uma data válida.',
            'expires_at.after_or_equal' => 'A data de expiração precisa ser hoje ou depois — um cupom que já nasce vencido não serve para nada.',
        ];
    }

    /**
     * Eventos que ainda podem receber um cupom novo.
     *
     * Prova já realizada fica de fora: não há mais inscrição para descontar.
     * Mesma regra do seletor de modalidades e kits.
     */
    private function eventosParaCupom()
    {
        return Event::where('organizer_id', $this->organizerId())
            ->where('event_date', '>=', now())
            ->orderBy('event_date')
            ->get(['id', 'title', 'event_date']);
    }

    /**
     * Os ids aceitos no campo de evento: os que aparecem no `<select>`, mais o
     * evento atual do cupom na edição.
     *
     * A lista é conferida aqui e não só montada na tela — o valor vem de um
     * `<select>`, que é entrada do usuário como qualquer outra, e trocá-lo no
     * navegador não pode abrir uma porta que a tela fechou.
     */
    private function idsDeEventosPermitidos(?Coupon $cupom): array
    {
        $ids = $this->eventosParaCupom()->pluck('id')->all();

        if ($cupom) {
            $ids[] = $cupom->event_id;
        }

        return array_values(array_unique($ids));
    }

    private function vezes(Coupon $cupom): string
    {
        return $cupom->used_quantity === 1
            ? '1 vez'
            : "{$cupom->used_quantity} vezes";
    }
}
