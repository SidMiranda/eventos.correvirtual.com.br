<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cidade do atleta.
 *
 * Nullable por dois motivos: quem já tem conta não tem cidade nenhuma e não
 * existe tela de editar perfil para preencher depois; e o campo é opcional no
 * cadastro (decisão do dono em 2026-09-22 — campo obrigatório novo no meio do
 * funil, no dia do lançamento, custa inscrição).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `nullOnDelete` e não cascade: município não se apaga, mas se um
            // dia sumir da lista do IBGE ninguém perde a conta por isso.
            $table->foreignId('city_id')->nullable()->after('sex')
                ->constrained('cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
    }
};
