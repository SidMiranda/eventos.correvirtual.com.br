<?php

namespace App\Services\Cobranca;

use Illuminate\Support\Facades\DB;

/**
 * Configurações da plataforma em `platform_settings` (chave/valor).
 *
 * Existe para valores que mudam sem deploy — hoje, a taxa por inscrição
 * (ADR 0008). O padrão garante que um banco sem a linha (teste, ambiente novo)
 * ainda responda algo sensato.
 */
final class ConfiguracaoDaPlataforma
{
    public const TAXA_INSCRICAO = 'plataforma_taxa_inscricao';

    private const PADROES = [
        self::TAXA_INSCRICAO => '0.70',
    ];

    public static function valor(string $chave): ?string
    {
        $valor = DB::table('platform_settings')->where('key', $chave)->value('value');

        return $valor ?? (self::PADROES[$chave] ?? null);
    }

    public static function definir(string $chave, string $valor): void
    {
        DB::table('platform_settings')->updateOrInsert(
            ['key' => $chave],
            ['value' => $valor, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** A taxa por inscrição paga, em reais. */
    public static function taxaDeInscricao(): float
    {
        return round((float) self::valor(self::TAXA_INSCRICAO), 2);
    }
}
