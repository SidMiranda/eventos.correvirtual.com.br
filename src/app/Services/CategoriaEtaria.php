<?php

namespace App\Services;

use App\Models\AgeCategory;
use App\Models\Event;
use App\Models\User;

/**
 * Em qual categoria etária o atleta cai, para um evento e um preço base.
 *
 * A data de nascimento vem do cadastro — a inscrição não pergunta. A idade é
 * contada pelo critério do evento (Event::idadeDe()). Em mais de uma
 * categoria, vale a de MAIOR DESCONTO EM REAIS sobre o preço base: é a única
 * forma de comparar "50%" com "R$ 30". Em nenhuma, ou sem nascimento, paga
 * integral. Ver ADR 0007.
 */
final class CategoriaEtaria
{
    public static function para(Event $evento, ?User $atleta, int $baseCentavos): ?AgeCategory
    {
        $idade = $evento->idadeDe($atleta?->birth_date);

        if ($idade === null) {
            return null;
        }

        return $evento->ageCategories()
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (AgeCategory $c) => $c->abrange($idade))
            ->sortByDesc(fn (AgeCategory $c) => $c->descontoEmCentavos($baseCentavos))
            ->first();
    }
}
