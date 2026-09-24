<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorias etárias: desconto por idade, não kit nem modalidade.
 *
 * Criança e idoso recebem o mesmo kit e pagam menos. A idade vem da data de
 * nascimento do cadastro, calculada pelo critério do evento
 * (`events.age_criteria`). Em mais de uma categoria, vale a de maior
 * desconto em reais. Ver docs/specs/precos-lotes-e-categorias.md.
 *
 * `discount_type` é string, como em `coupons`: enum em MySQL é ALTER TABLE
 * com a tabela travada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('name');

            // Qualquer um dos dois pode ser nulo ("até 12", "a partir de 60").
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();

            $table->string('discount_type', 10); // percent | amount
            $table->decimal('discount_value', 8, 2);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('age_categories');
    }
};
