<?php

use App\Support\PrecosLegados;
use Illuminate\Database\Migrations\Migration;

/**
 * Leva os eventos que já existiam para a estrutura nova: "Lote 1" aberto,
 * kit vinculado a todas as modalidades, e o preço de cada kit copiado para
 * cada (modalidade, kit) nesse lote. Tamanhos de camiseta para todo kit que
 * não seja "sem camiseta".
 *
 * A lógica mora em App\Support\PrecosLegados (testada); aqui só a chamada.
 * Referenciar código da aplicação de dentro de uma migration é um acordo
 * consciente: se PrecosLegados mudar de assinatura, esta migration já rodou
 * em todos os ambientes que importam. Ver ADR 0007.
 */
return new class extends Migration
{
    public function up(): void
    {
        $feito = PrecosLegados::migrar();

        if (! app()->runningUnitTests()) {
            echo sprintf(
                "  [precos] lotes: %d, vinculos kit-modalidade: %d, precos: %d, tamanhos: %d\n",
                $feito['lotes'], $feito['vinculos'], $feito['precos'], $feito['tamanhos']
            );
        }
    }

    public function down(): void
    {
        // As tabelas novas caem nas migrations anteriores; nada a desfazer aqui.
    }
};
