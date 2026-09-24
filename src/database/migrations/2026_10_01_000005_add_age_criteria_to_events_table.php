<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Como o evento conta a idade do atleta para a categoria etária.
 *
 * `calendar_year` (padrão): ano do evento − ano de nascimento. Quem faz 60 em
 * dezembro já conta 60 em janeiro — é o critério das federações.
 * `exact_date`: anos completos na data do evento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('age_criteria', 20)->default('calendar_year')->after('registration_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('age_criteria');
        });
    }
};
