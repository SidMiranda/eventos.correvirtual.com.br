<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A grade de preços: quanto custa (modalidade, kit) em cada lote.
 *
 * É aqui que o valor mora a partir da fatia 2 de 2026-09-23. `event_kits.price`
 * continua na tabela, sem uso no checkout — coluna com dado histórico não se
 * apaga num sistema no ar, e é dela que os preços foram migrados (ADR 0007).
 *
 * `event_id` é redundante (dá para chegar por qualquer uma das três FKs) e
 * está aqui de propósito: é a coluna pela qual o painel isola por organizador
 * sem três joins, e a que deixa um preço fora do evento saltar aos olhos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('modality_id')->constrained('event_modalities')->cascadeOnDelete();
            $table->foreignId('kit_id')->constrained('event_kits')->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained('event_lots')->cascadeOnDelete();

            $table->decimal('price', 10, 2);
            $table->timestamps();

            // Uma célula da grade só.
            $table->unique(['modality_id', 'kit_id', 'lot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_prices');
    }
};
