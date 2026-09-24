<?php

namespace App\Http\Controllers\Admin;

use App\Models\Event;
use App\Models\EventLot;
use Illuminate\Http\Request;

/**
 * Lotes de um evento: as janelas de vigência da grade de preços.
 *
 * O lote não tem preço próprio — ele é uma coluna da grade
 * (/admin/eventos/{id}/precos). O sistema resolve sozinho qual está vigente;
 * aqui só se cadastram as janelas. Ver ADR 0007.
 */
class EventLotController extends AdminController
{
    public function index(int $eventoId)
    {
        $event = $this->eventoDoOrganizador($eventoId);
        $lots = $event->lots()->get();

        return view('admin.lots.index', compact('event', 'lots'));
    }

    public function create(int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        return view('admin.lots.create', compact('event'));
    }

    public function store(Request $request, int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        $event->lots()->create($this->validar($request));

        return redirect()
            ->route('admin.eventos.lotes.index', $event->id)
            ->with('sucesso', 'Lote criado. Não esqueça de preencher a coluna dele na grade de preços.');
    }

    public function edit(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $lot = $this->lote($event, $id);

        return view('admin.lots.edit', compact('event', 'lot'));
    }

    public function update(Request $request, int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $lot = $this->lote($event, $id);

        $lot->update($this->validar($request));

        return redirect()
            ->route('admin.eventos.lotes.index', $event->id)
            ->with('sucesso', 'Lote atualizado.');
    }

    public function destroy(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $lot = $this->lote($event, $id);

        // Inscrição guarda o lote em que foi feita. Apagar o lote não apaga a
        // inscrição (nullOnDelete), mas o organizador perderia a resposta a
        // "quantos entraram no Lote 1?". Desativar resolve o mesmo problema.
        if ($lot->subscriptions()->exists()) {
            return back()->withErrors([
                'lote' => 'Este lote já tem inscrições e por isso não pode ser apagado. Desative-o para ele deixar de valer.',
            ]);
        }

        // Os preços daquela coluna caem junto (cascade) — é o esperado.
        $lot->delete();

        return redirect()
            ->route('admin.eventos.lotes.index', $event->id)
            ->with('sucesso', 'Lote apagado.');
    }

    private function lote(Event $event, int $id): EventLot
    {
        return $event->lots()->where('id', $id)->firstOrFail();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'max_subscriptions' => ['nullable', 'integer', 'min:1'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'active' => ['boolean'],
        ], [
            'ends_at.after' => 'O fim do lote precisa ser depois do início.',
            'max_subscriptions.min' => 'O limite precisa ser de pelo menos 1 inscrição. Deixe em branco para não limitar.',
        ]) + [
            'active' => $request->boolean('active'),
            'position' => (int) $request->input('position', 0),
        ];
    }
}
