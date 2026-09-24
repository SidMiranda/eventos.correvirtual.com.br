<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventLot;
use App\Models\EventPrice;
use App\Models\KitOption;
use App\Models\Subscription;

/**
 * Leva um evento da estrutura antiga (preço no kit) para a nova (lote + grade).
 *
 * Roda na migration de 2026-10-01 para os eventos que já existiam, e fica
 * disponível como `php artisan eventos:migrar-precos` para rodar de novo. É
 * idempotente: só cria o que falta, nunca sobrescreve o que o organizador já
 * mexeu.
 *
 * Vive numa classe de suporte, e não dentro da migration, para poder ser
 * TESTADA: o teste monta um evento como ele era, roda, e confere que cada
 * (modalidade, kit) custa exatamente o `event_kits.price` de antes. É o
 * mesmo caminho que produção percorre — por isso os testes de inscrição
 * também usam isto para preparar o evento (ADR 0007).
 */
final class PrecosLegados
{
    public const NOME_DO_LOTE = 'Lote 1';

    /**
     * Kit cujo nome contém isto NÃO ganha tamanhos de camiseta.
     *
     * É heurística, e é a única desta migração. Sem ela, o kit "Sem camiseta"
     * que está à venda em produção passaria a exigir tamanho no dia seguinte
     * ao lançamento. O organizador ajusta no formulário do kit.
     */
    public const SEM_CAMISETA = 'sem camiseta';

    /** @return array<string,int> o que foi criado, por tipo */
    public static function migrar(): array
    {
        $total = ['lotes' => 0, 'vinculos' => 0, 'precos' => 0, 'tamanhos' => 0];

        Event::query()->orderBy('id')->each(function (Event $evento) use (&$total) {
            foreach (self::migrarEvento($evento) as $tipo => $n) {
                $total[$tipo] += $n;
            }
        });

        return $total;
    }

    /** @return array<string,int> */
    public static function migrarEvento(Event $evento): array
    {
        $feito = ['lotes' => 0, 'vinculos' => 0, 'precos' => 0, 'tamanhos' => 0];

        $lote = self::loteInicial($evento, $feito);

        $modalidades = $evento->modalities()->get();
        $kits = $evento->kits()->get();

        foreach ($kits as $kit) {
            // 2. Kit sem vínculo → todas as modalidades do evento.
            if ($kit->modalities()->count() === 0 && $modalidades->isNotEmpty()) {
                $kit->modalities()->attach($modalidades->pluck('id'));
                $feito['vinculos'] += $modalidades->count();
            }

            // 3. Cada (modalidade, kit) sem preço no lote → o preço do kit.
            foreach ($kit->modalities()->get() as $modalidade) {
                $existe = EventPrice::where('modality_id', $modalidade->id)
                    ->where('kit_id', $kit->id)
                    ->where('lot_id', $lote->id)
                    ->exists();

                if (! $existe) {
                    EventPrice::create([
                        'event_id' => $evento->id,
                        'modality_id' => $modalidade->id,
                        'kit_id' => $kit->id,
                        'lot_id' => $lote->id,
                        'price' => $kit->price,
                    ]);
                    $feito['precos']++;
                }
            }

            // 4. Tamanhos, exceto para o kit sem camiseta.
            $feito['tamanhos'] += self::tamanhosIniciais($kit);
        }

        return $feito;
    }

    private static function loteInicial(Event $evento, array &$feito): EventLot
    {
        $lote = $evento->lots()->orderBy('position')->orderBy('id')->first();

        if ($lote) {
            return $lote;
        }

        $feito['lotes']++;

        return $evento->lots()->create([
            'name' => self::NOME_DO_LOTE,
            'starts_at' => now(),
            'ends_at' => null,
            'max_subscriptions' => null,
            'position' => 0,
            'active' => true,
        ]);
    }

    private static function tamanhosIniciais(EventKit $kit): int
    {
        if ($kit->options()->where('attribute', KitOption::TAMANHO)->exists()) {
            return 0;
        }

        if (str_contains(mb_strtolower($kit->name), self::SEM_CAMISETA)) {
            return 0;
        }

        $posicao = 0;
        foreach (Subscription::tamanhosDeCamiseta() as $tamanho) {
            $kit->options()->create([
                'attribute' => KitOption::TAMANHO,
                'value' => $tamanho,
                'position' => $posicao++,
            ]);
        }

        return $posicao;
    }
}
