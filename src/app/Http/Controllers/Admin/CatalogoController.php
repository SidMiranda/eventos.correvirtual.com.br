<?php

namespace App\Http\Controllers\Admin;

use App\Models\AgeCategory;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventModality;
use Illuminate\Http\Request;

/**
 * Visão geral de modalidades e kits, atravessando todos os eventos.
 *
 * Modalidade e kit pertencem a um evento — não existe "modalidade do
 * organizador". Mas obrigar a passar pelo evento toda vez que se quer olhar ou
 * cadastrar é chato, então estas telas dão o atalho pelo menu lateral: listam
 * tudo com a coluna do evento, e o botão de cadastrar pergunta em qual evento
 * antes de mandar para o formulário aninhado de sempre.
 */
class CatalogoController extends AdminController
{
    public function modalidades()
    {
        $modalities = EventModality::whereHas('event', $this->doOrganizador())
            ->with('event')
            ->join('events', 'events.id', '=', 'event_modalities.event_id')
            ->orderByDesc('events.event_date')
            ->orderBy('event_modalities.distance_km')
            ->select('event_modalities.*')
            ->paginate(30);

        return view('admin.modalities.geral', [
            'modalities' => $modalities,
            'eventos' => $this->eventosParaEscolha(),
        ]);
    }

    public function kits()
    {
        $kits = EventKit::whereHas('event', $this->doOrganizador())
            ->with('event')
            ->join('events', 'events.id', '=', 'event_kits.event_id')
            ->orderByDesc('events.event_date')
            ->orderBy('event_kits.price')
            ->select('event_kits.*')
            ->paginate(30);

        return view('admin.kits.geral', [
            'kits' => $kits,
            'eventos' => $this->eventosParaEscolha(),
        ]);
    }

    public function lotes()
    {
        $lots = EventLot::whereHas('event', $this->doOrganizador())
            ->with('event')
            ->join('events', 'events.id', '=', 'event_lots.event_id')
            ->orderByDesc('events.event_date')
            ->orderBy('event_lots.position')
            ->select('event_lots.*')
            ->paginate(30);

        return view('admin.lots.geral', [
            'lots' => $lots,
            'eventos' => $this->eventosParaEscolha(),
        ]);
    }

    public function categorias()
    {
        $categories = AgeCategory::whereHas('event', $this->doOrganizador())
            ->with('event')
            ->join('events', 'events.id', '=', 'age_categories.event_id')
            ->orderByDesc('events.event_date')
            ->orderBy('age_categories.min_age')
            ->select('age_categories.*')
            ->paginate(30);

        return view('admin.age_categories.geral', [
            'categories' => $categories,
            'eventos' => $this->eventosParaEscolha(),
        ]);
    }

    /**
     * Recebe o evento escolhido no seletor e manda para o formulário aninhado.
     *
     * Existe para o botão do menu lateral poder cadastrar sem que o usuário
     * tenha que achar o evento na lista antes. O evento é validado contra os
     * do organizador — o valor vem de um <select>, que é entrada do usuário
     * como qualquer outra.
     */
    public function novo(Request $request, string $tipo)
    {
        $eventoId = (int) $request->query('evento');

        $evento = Event::where('id', $eventoId)
            ->where('organizer_id', $this->organizerId())
            ->first();

        // Evento já realizado é recusado aqui também, não só escondido do
        // seletor: o valor vem de um <select>, e trocar isso no navegador não
        // pode abrir uma porta que a tela fechou.
        // O tipo já vem restrito pelo whereIn da rota; o mapa só traduz.
        [$voltar, $criar] = [
            'modalidades' => ['admin.modalidades.geral', 'admin.eventos.modalidades.create'],
            'kits'        => ['admin.kits.geral', 'admin.eventos.kits.create'],
            'lotes'       => ['admin.lotes.geral', 'admin.eventos.lotes.create'],
            'categorias'  => ['admin.categorias.geral', 'admin.eventos.categorias.create'],
        ][$tipo];

        if (!$evento || $evento->jaAconteceu()) {
            return redirect()
                ->route($voltar)
                ->withErrors(['evento' => 'Escolha um evento seu que ainda não aconteceu.']);
        }

        return redirect()->route($criar, $eventoId);
    }

    private function doOrganizador(): callable
    {
        $organizerId = $this->organizerId();

        return fn ($q) => $q->where('organizer_id', $organizerId);
    }

    /**
     * Eventos que ainda podem receber modalidade ou kit.
     *
     * Prova que já aconteceu fica de fora: não há o que cadastrar nela, e
     * deixá-la na lista só convida ao engano.
     */
    private function eventosParaEscolha()
    {
        return Event::where('organizer_id', $this->organizerId())
            ->where('event_date', '>=', now())
            ->orderBy('event_date')
            ->get(['id', 'title', 'event_date']);
    }
}
