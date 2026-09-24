<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma variação do kit — hoje, um tamanho de camiseta disponível.
 *
 * `attribute` existe para cor entrar amanhã sem migration nova; só
 * `shirt_size` é implementado. O rótulo com a medida ("GG (59 x 76 cm)") vem
 * de Subscription::CAMISETAS — aqui só o código.
 */
class KitOption extends Model
{
    use HasFactory;

    public const TAMANHO = 'shirt_size';

    protected $fillable = ['kit_id', 'attribute', 'value', 'position'];

    public function kit()
    {
        return $this->belongsTo(EventKit::class, 'kit_id');
    }
}
