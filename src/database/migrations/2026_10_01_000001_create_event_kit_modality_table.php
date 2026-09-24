<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Em quais modalidades cada kit pode ser comprado.
 *
 * N:N e não um `modality_id` no kit: a compatibilidade com os eventos que já
 * existiam exige "kit disponível em todas as modalidades do evento", e o
 * mesmo Kit Completo servindo 5K e 10K é o caso comum. É este vínculo que
 * impede o atleta de escolher uma modalidade e levar o kit de outra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_kit_modality', function (Blueprint $table) {
            $table->foreignId('kit_id')->constrained('event_kits')->cascadeOnDelete();
            $table->foreignId('modality_id')->constrained('event_modalities')->cascadeOnDelete();

            $table->primary(['kit_id', 'modality_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_kit_modality');
    }
};
