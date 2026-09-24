<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variações do kit — o que o atleta escolhe na hora de comprar.
 *
 * Hoje só `shirt_size` (tamanho da camiseta). A coluna `attribute` existe
 * para uma cor entrar amanhã sem migration nova; nada além de tamanho é
 * implementado agora (briefing de 2026-09-23).
 *
 * Substitui o campo solto de 2026-09-22, em que todo kit — inclusive o "sem
 * camiseta" — oferecia os mesmos dez tamanhos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_id')->constrained('event_kits')->cascadeOnDelete();

            // 'shirt_size' hoje; 'color' quando vier.
            $table->string('attribute', 40);
            // 'P', 'GG', 'BLM'... O rótulo com a medida vem da tabela em
            // Subscription::CAMISETAS — aqui só o código.
            $table->string('value', 20);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['kit_id', 'attribute', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_options');
    }
};
