<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Município brasileiro, na lista do IBGE.
 *
 * Tabela de referência: não pertence a organizador nenhum e é a mesma para
 * todos os sites da plataforma. Ver `cidades:importar`.
 */
class City extends Model
{
    use HasFactory;

    protected $fillable = ['ibge_code', 'name', 'name_normalized', 'state'];

    /** Quantos municípios a busca devolve de uma vez. */
    public const LIMITE_DA_BUSCA = 20;

    /** A partir de quantas letras a busca vale a pena. */
    public const MINIMO_DE_LETRAS = 3;

    /**
     * O nome como ele é digitado: sem acento, em minúsculas.
     *
     * Ninguém escreve "São Paulo" com til numa caixa de busca, e "Mogi Guaçu"
     * com ç é ainda menos provável no celular.
     */
    public static function normalizar(string $nome): string
    {
        return Str::lower(Str::ascii($nome));
    }

    public function nomeCompleto(): string
    {
        return "{$this->name} - {$this->state}";
    }

    /**
     * Busca por pedaço do nome, com os que COMEÇAM pelo termo primeiro.
     *
     * Sem essa ordenação, quem digita "mogi" recebe "Alto Mogi" antes de
     * "Mogi Guaçu" — o que se procura é quase sempre o começo.
     */
    public function scopeBuscar(Builder $query, string $termo): Builder
    {
        $termo = self::normalizar(trim($termo));

        return $query
            ->where('name_normalized', 'like', "%{$termo}%")
            ->orderByRaw('CASE WHEN name_normalized LIKE ? THEN 0 ELSE 1 END', ["{$termo}%"])
            ->orderBy('name_normalized')
            ->orderBy('state');
    }
}
