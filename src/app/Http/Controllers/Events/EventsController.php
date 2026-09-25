<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Sponsor;
use App\Models\Subscription;
use App\Support\Arquivos;
use App\Support\GaleriaDeRealizados;

class EventsController extends Controller
{
    public function index()
    {
        $organizerId = app('currentOrganizer')->id;

        $events = Event::with('modalities')
            ->where('active', true)
            ->where('organizer_id', $organizerId)
            ->get();

        // Duas listas, com ordens opostas de propósito: o que ainda vai
        // acontecer sobe pelo mais próximo (é onde o atleta se inscreve), e o
        // que já passou desce pelo mais recente (é histórico — a prova do ano
        // passado interessa mais que a de cinco anos atrás).
        $proximosEventos = $events
            ->filter(fn ($e) => !$e->jaAconteceu())
            ->sortBy('event_date')
            ->values();

        $eventosPassados = $events
            ->filter(fn ($e) => $e->jaAconteceu())
            ->sortByDesc('event_date')
            ->values();

        // A vitrine de realizados não é uma lista de eventos: é uma lista de
        // artes, e boa parte dela é de provas anteriores à plataforma, que não
        // têm registro no banco. Quem junta as duas fontes é a GaleriaDeRealizados.
        $eventosRealizados = GaleriaDeRealizados::montar($organizerId, $eventosPassados);

        // Os patrocinadores vêm do cadastro do painel, não mais de SVG colado
        // na view: trocar um deixou de exigir deploy.
        $patrocinadores = Sponsor::naVitrine($organizerId)->get();

        // A galeria de fotos: 12 é o que a grade de 6×2 do desktop mostra; o
        // celular esconde da 7ª em diante por CSS.
        $fotos = Photo::naVitrine($organizerId)->limit(12)->get();

        return view('index', compact('proximosEventos', 'eventosRealizados', 'patrocinadores', 'fotos'));
    }

    public function show($event_id)
    {
        $organizerId = app('currentOrganizer')->id;

        // Busca o evento pelo ID e já carrega as modalidades e os kits associados
        $event = Event::with(['modalities', 'kits'])
            ->where('organizer_id', $organizerId)
            ->findOrFail($event_id);

        // Cartão de pré-visualização do link: quando alguém manda a página deste
        // evento no WhatsApp, é a corrida que precisa aparecer — não a capa
        // genérica do organizador.
        $og = [
            'tipo' => 'article',
            'titulo' => $event->title . ' — ' . $event->event_date?->format('d/m/Y'),
            'descricao' => $event->location . '. ' . $event->description,
            'imagem' => Arquivos::ogDoEvento($event),
        ];

        // Quem já se inscreveu vê a situação no lugar do "Inscreva-se".
        // Cancelada não conta: ela pode se inscrever de novo.
        $minhaInscricao = auth()->check()
            ? Subscription::where('event_id', $event->id)
                ->where('user_id', auth()->id())
                ->whereIn('status', ['pending', 'paid'])
                ->first()
            : null;

        return view('events.event-details', compact('event', 'og', 'minhaInscricao'));
    }
}
