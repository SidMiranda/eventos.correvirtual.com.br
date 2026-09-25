<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * As inscrições de um atleta no organizador do site, já separadas em
 * próximas e realizadas — a tela só desenha (ver docs/specs/area-do-atleta.md).
 *
 * "Realizada" é o evento que já aconteceu (Event::jaAconteceu), não a
 * inscrição paga: uma pendente de prova passada também vai para baixo, porque
 * não há mais o que fazer com ela.
 */
final class InscricoesDoAtleta
{
    /**
     * @param Collection<int, Subscription> $proximas  da prova mais perto para a mais longe
     * @param Collection<int, Subscription> $realizadas da mais recente para a mais antiga
     */
    private function __construct(
        public readonly Collection $proximas,
        public readonly Collection $realizadas,
    ) {
    }

    public static function de(User $atleta, int $organizerId): self
    {
        $todas = Subscription::with(['event', 'modality', 'kit.options', 'coupon', 'ageCategory'])
            ->where('user_id', $atleta->id)
            ->whereHas('event', fn ($q) => $q->where('organizer_id', $organizerId))
            ->get();

        [$realizadas, $proximas] = $todas->partition(fn (Subscription $s) => $s->event->jaAconteceu());

        return new self(
            proximas: $proximas->sortBy(fn (Subscription $s) => $s->event->event_date)->values(),
            realizadas: $realizadas->sortByDesc(fn (Subscription $s) => $s->event->event_date)->values(),
        );
    }

    public function vazia(): bool
    {
        return $this->proximas->isEmpty() && $this->realizadas->isEmpty();
    }
}
