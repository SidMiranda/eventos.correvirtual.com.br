<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurações da plataforma (não de um organizador), em chave/valor.
 *
 * Nasce para a taxa por inscrição (ADR 0008): mudar o valor não pode exigir
 * deploy nem nova autorização do organizador. Ver App\Services\Cobranca\
 * ConfiguracaoDaPlataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->nullable();
            $table->timestamps();
        });

        DB::table('platform_settings')->insert([
            'key' => 'plataforma_taxa_inscricao',
            'value' => '0.70',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
