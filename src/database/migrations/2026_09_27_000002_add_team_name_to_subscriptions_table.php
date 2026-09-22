<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A equipe do atleta na inscrição — texto livre, por enquanto.
 *
 * Existe `teams` desde 2026-08-29, com equipe cadastrada pelo organizador e
 * marcada como aberta ou fechada. Não é o que entra aqui: decisão do dono em
 * 2026-09-22 foi deixar o atleta digitar o nome da equipe dele, sem depender
 * de cadastro prévio — na primeira prova ninguém sabe ainda quais assessorias
 * vão aparecer.
 *
 * Por isso `team_name` e não `team_id`: o nome do campo deixa claro que é
 * texto solto, e guarda o lugar para o vínculo de verdade quando ele vier.
 * O valor é gravado em CAIXA ALTA, para "corre mogi" e "CORRE MOGI" não
 * virarem duas equipes diferentes na hora de contar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('team_name', 50)->nullable()->after('kit_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });
    }
};
