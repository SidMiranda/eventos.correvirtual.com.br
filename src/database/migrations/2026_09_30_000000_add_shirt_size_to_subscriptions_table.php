<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O tamanho da camiseta escolhido na inscrição.
 *
 * Uma coluna só, por decisão do dono (2026-09-22): a tabela de medidas é a
 * mesma para todos os eventos hoje, e o fluxo certo — cada evento declarando
 * que camisetas oferece, e o tamanho sendo exigido apenas nos kits que têm
 * camiseta — fica para depois.
 *
 * `string` e não enum: os tamanhos mudam de fornecedor para fornecedor, e
 * alterar enum em MySQL é ALTER TABLE com a tabela travada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('shirt_size', 10)->nullable()->after('team_name');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('shirt_size');
        });
    }
};
