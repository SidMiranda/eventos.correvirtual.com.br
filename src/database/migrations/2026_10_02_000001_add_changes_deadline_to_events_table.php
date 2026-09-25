<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Alterações até" (2026-09-25): até quando o atleta troca a camiseta e a
 * equipe pela "Minha conta" — depois disso o organizador já mandou produzir.
 *
 * Nullable: vazio vale o encerramento das inscrições (Event::prazoDeAlteracoes).
 * Ver docs/specs/area-do-atleta.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('changes_deadline')->nullable()->after('registration_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('changes_deadline');
        });
    }
};
