<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cronograma da prova, que até aqui era texto fixo no Blade.
 *
 * A página do evento sempre mostrou o mesmo bloco — "04h abertura do
 * estacionamento, 05h30 largada 10km..." — em todo evento, vindo do tempo em
 * que o catálogo era mocado. Uma prova que larga às 7h e não tem 10km exibia
 * horários errados para o atleta, e o organizador não tinha onde corrigir.
 *
 * `text` e não `string`: são várias linhas, uma por horário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Nullable: evento sem cronograma é normal (ainda não fechou a
            // programação), e nesse caso o bloco simplesmente não aparece na
            // página — melhor que mostrar horário de outra prova.
            $table->text('schedule')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }
};
