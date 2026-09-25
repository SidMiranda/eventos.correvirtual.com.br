<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoa com deficiência (2026-09-25).
 *
 * Por enquanto é só informação para o organizador (decisão do dono): marcado
 * no cadastro, consultado no painel, sai nos relatórios. Nenhuma regra de
 * preço, categoria ou largada depende disto. Quem já tem conta fica `false`
 * até existir a tela de perfil do atleta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pcd')->default(false)->after('guardian_cpf');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_pcd');
        });
    }
};
