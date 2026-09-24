<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\KitOption;
use App\Support\PrecosLegados;

/**
 * Deixa um evento de teste vendável na estrutura de 2026-10 (ADR 0007).
 *
 * Desde a fatia 2, inscrever exige lote vigente, kit vinculado à modalidade e
 * preço na grade. Os testes antigos criavam só modalidade e kit — este helper
 * faz o resto pelo MESMO caminho que produção percorreu (PrecosLegados), que
 * é reaproveitar, não atalhar.
 *
 * Os tamanhos são retirados por padrão: a migração dá os 10 tamanhos a todo
 * kit que não seja "sem camiseta", o que tornaria o tamanho obrigatório em
 * testes de cupom, cancelamento e equipe, que não são sobre isso. Quem testa
 * tamanho pede `comTamanhos: true`.
 */
trait PreparaEventoParaVenda
{
    protected function prepararParaVenda(Event $evento, bool $comTamanhos = false): void
    {
        PrecosLegados::migrarEvento($evento);

        if (! $comTamanhos) {
            KitOption::whereIn('kit_id', $evento->kits()->pluck('id'))->delete();
        }
    }
}
