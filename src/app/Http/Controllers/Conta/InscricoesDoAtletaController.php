<?php

namespace App\Http\Controllers\Conta;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\AlteracaoDeInscricao;
use App\Support\InscricoesDoAtleta;
use Illuminate\Http\Request;

/**
 * "Minha conta" › Inscrições: a lista e a edição de uma inscrição.
 * Ver docs/specs/area-do-atleta.md.
 */
class InscricoesDoAtletaController extends Controller
{
    public function index()
    {
        $inscricoes = InscricoesDoAtleta::de(auth()->user(), app('currentOrganizer')->id);

        return view('conta.inscricoes', compact('inscricoes'));
    }

    public function edit(int $id)
    {
        $inscricao = $this->daPropriaConta($id);

        return view('conta.inscricao', [
            'inscricao' => $inscricao,
            'alteracao' => AlteracaoDeInscricao::para($inscricao),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $inscricao = $this->daPropriaConta($id);
        $alteracao = AlteracaoDeInscricao::para($inscricao);

        if (! $alteracao->permitida()) {
            return back()->withErrors(['inscricao' => $alteracao->motivo()]);
        }

        $request->validate([
            'equipe' => Subscription::REGRA_DA_EQUIPE,
            'camiseta' => ['nullable', 'string', 'max:20'],
        ], Subscription::MENSAGENS_DA_EQUIPE);

        $dados = ['team_name' => Subscription::normalizarEquipe($request->input('equipe'))];

        // Kit sem camiseta: o campo nem aparece, e o que vier é ignorado.
        if ($alteracao->temCamiseta()) {
            if (! $alteracao->aceitaTamanho($request->input('camiseta'))) {
                return back()->withInput()->withErrors(['camiseta' => 'Escolha o tamanho da camiseta entre os oferecidos pelo seu kit.']);
            }

            $dados['shirt_size'] = $request->input('camiseta');
        }

        // Só equipe e camiseta: modalidade, kit e valores não mudam por aqui
        // (mudariam o preço — ver o spec).
        $inscricao->update($dados);

        return redirect()->route('conta.inscricoes')->with('success', 'Inscrição em ' . $inscricao->event->title . ' atualizada.');
    }

    /**
     * A inscrição do atleta logado, num evento do organizador do site. A de
     * outra pessoa (ou de outro site) é 404, não 403: não se confirma que ela
     * existe.
     */
    private function daPropriaConta(int $id): Subscription
    {
        return Subscription::with(['event', 'modality', 'kit.options'])
            ->where('user_id', auth()->id())
            ->whereHas('event', fn ($q) => $q->where('organizer_id', app('currentOrganizer')->id))
            ->findOrFail($id);
    }
}
