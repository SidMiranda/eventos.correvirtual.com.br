<?php

namespace App\Models;

use App\Support\Arquivos;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma foto da galeria da home.
 *
 * Pertence ao organizador, como equipe e patrocinador. A imagem não é coluna:
 * o caminho no bucket é derivado do organizador e do id (ver
 * App\Support\ImagensDaFoto), e "linha existe" quer dizer "imagem existe".
 *
 * Ver docs/specs/galeria-de-fotos.md.
 */
class Photo extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'caption',
        'link_url',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    /**
     * As que aparecem na home: do organizador atual, ativas, na ordem que ele
     * definiu — e, no empate, a mais nova primeiro (é um feed).
     */
    public function scopeNaVitrine($query, int $organizerId)
    {
        return $query->where('organizer_id', $organizerId)
            ->where('active', true)
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function urlDaImagem(): string
    {
        return Arquivos::fotoDaGaleria($this);
    }
}
