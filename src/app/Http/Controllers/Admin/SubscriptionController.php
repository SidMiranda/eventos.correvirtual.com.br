<?php

namespace App\Http\Controllers\Admin;

use App\Models\Event;
use App\Models\Subscription;
use App\Support\FiltroDeInscricoes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Quem se inscreveu nos eventos do organizador.
 *
 * Até aqui o painel contava inscrições no dashboard e parava nisso: não havia
 * lista, filtro nem relatório — o organizador não tinha como saber quem
 * estava inscrito nas provas dele.
 *
 * A tela e o PDF usam o MESMO recorte (App\Support\FiltroDeInscricoes): um
 * relatório que discorda da tela que o gerou é pior que não ter relatório.
 *
 * Ver docs/specs/gestao-de-inscricoes.md.
 */
class SubscriptionController extends AdminController
{
    public function index(Request $request)
    {
        $filtro = FiltroDeInscricoes::daRequisicao($request);

        $inscricoes = $filtro->aplicar($this->doOrganizador())
            ->with(['user', 'event', 'modality', 'kit', 'coupon'])
            ->orderByDesc('subscriptions.created_at')
            ->paginate(30)
            ->withQueryString();

        return view('admin.subscriptions.index', [
            'inscricoes' => $inscricoes,
            'totais' => $this->totais($filtro),
            'eventos' => $this->eventosDoOrganizador(),
            'filtro' => $filtro,
        ]);
    }

    /**
     * O relatório em PDF.
     *
     * Exige um evento escolhido: uma lista de inscritos de provas diferentes
     * misturadas não serve para nada no papel — é por evento que se confere
     * largada, kit e lote.
     *
     * Vai `inline` e não `attachment`: o pedido foi abrir em aba nova, não
     * baixar. O `target="_blank"` do link cuida da aba; é este cabeçalho que
     * faz o navegador exibir em vez de salvar.
     */
    public function pdf(Request $request)
    {
        $filtro = FiltroDeInscricoes::daRequisicao($request);

        if (! $filtro->evento) {
            return redirect()
                ->route('admin.inscricoes.index', $filtro->paraUrl())
                ->withErrors(['evento' => 'Escolha um evento para gerar o relatório: ele é a lista de inscritos de uma prova.']);
        }

        // 404 e não 403 para evento de outro organizador, como no resto do
        // painel: não é para descobrir que o evento do vizinho existe.
        $evento = $this->eventoDoOrganizador($filtro->evento);

        $inscricoes = $filtro->aplicar($this->doOrganizador())
            ->with(['user', 'modality', 'kit', 'coupon'])
            ->orderBy('users.name')
            ->join('users', 'users.id', '=', 'subscriptions.user_id')
            ->select('subscriptions.*')
            ->get();

        $pdf = Pdf::loadView('admin.subscriptions.pdf', [
            'evento' => $evento,
            'organizador' => $this->organizer(),
            'inscricoes' => $inscricoes,
            'totais' => $this->totais($filtro),
            'filtro' => $filtro,
        ])->setPaper('a4');

        $nome = 'inscritos-' . $evento->slug . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->stream($nome);
    }

    /*
    |--------------------------------------------------------------------------
    | Apoio
    |--------------------------------------------------------------------------
    */

    /**
     * A base de toda consulta desta tela: só inscrições em eventos do
     * organizador logado. `subscriptions` não tem organizer_id — o vínculo é
     * o evento, como já vale no DashboardController.
     */
    private function doOrganizador()
    {
        $organizerId = $this->organizerId();

        return Subscription::whereHas('event', fn ($q) => $q->where('organizer_id', $organizerId));
    }

    /**
     * Os números do recorte atual.
     *
     * Saem de `subscriptions` (`price`, `discount_amount`), que guardam o que
     * foi cobrado de verdade — sem reconstruir nada a partir do kit (que muda
     * de preço) ou do cupom (que é editável). Ver o retrato financeiro em
     * docs/specs/cupons-de-desconto.md.
     */
    private function totais(FiltroDeInscricoes $filtro): array
    {
        $base = fn () => $filtro->aplicar($this->doOrganizador());

        return [
            'inscricoes' => $base()->count(),
            'pagas' => $base()->where('subscriptions.status', Subscription::PAGA)->count(),
            'pendentes' => $base()->where('subscriptions.status', Subscription::PENDENTE)->count(),
            'canceladas' => $base()->where('subscriptions.status', Subscription::CANCELADA)->count(),
            // Só o que entrou: inscrição pendente ainda não é dinheiro.
            'arrecadado' => (float) $base()->where('subscriptions.status', Subscription::PAGA)->sum('subscriptions.price'),
            'descontos' => (float) $base()->where('subscriptions.status', Subscription::PAGA)->sum('subscriptions.discount_amount'),
        ];
    }

    private function eventosDoOrganizador()
    {
        return Event::where('organizer_id', $this->organizerId())
            ->orderByDesc('event_date')
            ->get(['id', 'title', 'event_date']);
    }
}
