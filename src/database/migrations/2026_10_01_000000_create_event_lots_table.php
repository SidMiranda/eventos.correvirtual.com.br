<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes do evento: janelas de vigência, não produtos.
 *
 * O lote diz qual coluna da grade de preços está valendo. O sistema resolve
 * o vigente na hora da inscrição pela data (e pela quantidade, se houver
 * limite) — não existe "ativar lote" manual. Ver ADR 0007 e
 * docs/specs/precos-lotes-e-categorias.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('name');

            $table->dateTime('starts_at');
            // Nulo = aberto até o fim das inscrições. É o caso do "Lote 1"
            // criado para os eventos que já existiam.
            $table->dateTime('ends_at')->nullable();

            // Nulo = sem limite. Com limite, o lote vira quando a quantidade de
            // inscrições não canceladas com este lot_id chega ao número.
            $table->unsignedInteger('max_subscriptions')->nullable();

            // Desempate quando dois lotes estão vigentes ao mesmo tempo: menor
            // primeiro. Também é a ordem das colunas na grade.
            $table->unsignedInteger('position')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lots');
    }
};
