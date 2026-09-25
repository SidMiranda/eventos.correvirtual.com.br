<?php

namespace App\Support;

use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * O que o atleta pode mudar numa inscrição já feita, e até quando
 * (docs/specs/area-do-atleta.md, fatia 3).
 *
 * Hoje: equipe e tamanho da camiseta, até o "Alterações até" do evento (ou o
 * encerramento das inscrições). Toda regra de "pode?" mora aqui — a tela e o
 * controller só perguntam. É o lugar do estoque por tamanho quando ele vier:
 * uma condição a mais em aceitaTamanho(), sem mexer em tela nenhuma.
 */
final class AlteracaoDeInscricao
{
    private function __construct(private readonly Subscription $inscricao)
    {
    }

    public static function para(Subscription $inscricao): self
    {
        return new self($inscricao->loadMissing(['event', 'kit.options']));
    }

    /** Por que não dá para alterar — null quando dá. */
    public function motivo(): ?string
    {
        $evento = $this->inscricao->event;

        if ($this->inscricao->cancelada()) {
            return 'Esta inscrição foi cancelada.';
        }

        if ($evento->jaAconteceu()) {
            return 'Este evento já aconteceu.';
        }

        $prazo = $this->prazo();

        if ($prazo !== null && $prazo->isPast()) {
            return 'O prazo para alterações terminou em ' . $prazo->format('d/m/Y \à\s H:i') . '.';
        }

        return null;
    }

    public function permitida(): bool
    {
        return $this->motivo() === null;
    }

    public function prazo(): ?Carbon
    {
        return $this->inscricao->event->prazoDeAlteracoes();
    }

    /** O kit da inscrição tem camiseta? Sem tamanho no kit, o campo nem aparece. */
    public function temCamiseta(): bool
    {
        return (bool) $this->inscricao->kit?->temTamanhos();
    }

    /** @return string[] códigos, na ordem da tabela de medidas */
    public function tamanhos(): array
    {
        return $this->inscricao->kit?->tamanhos() ?? [];
    }

    public function aceitaTamanho(?string $codigo): bool
    {
        return $codigo !== null && in_array($codigo, $this->tamanhos(), true);
    }
}
