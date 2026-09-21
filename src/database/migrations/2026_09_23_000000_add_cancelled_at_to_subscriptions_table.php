<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quando a inscrição foi cancelada.
 *
 * Até aqui cancelar APAGAVA a linha (decisão do BUG-003, em 2026-07-30), e por
 * isso o status `cancelled` — que o enum sempre aceitou — nunca foi usado. O
 * efeito colateral é que o organizador não tinha como saber que alguém desistiu:
 * a inscrição simplesmente sumia.
 *
 * A partir de 2026-09-21 a inscrição cancelada fica, com a data do cancelamento,
 * e vira um filtro na tela de gestão. Ver docs/specs/gestao-de-inscricoes.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
