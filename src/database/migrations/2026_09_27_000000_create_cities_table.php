<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os municípios brasileiros, para a cidade do atleta ser um vínculo e não um
 * texto digitado.
 *
 * A lista é a do IBGE (5.571 municípios), importada de um arquivo versionado
 * em `database/data/municipios-ibge.json` por `php artisan cidades:importar`.
 * O arquivo fica no repositório de propósito: consultar a API do IBGE durante
 * o deploy tornaria a subida dependente de um serviço de fora estar no ar, e
 * a lista muda de anos em anos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();

            // O código oficial do IBGE é a chave natural: é por ele que o
            // import reconhece a cidade que já existe, e é ele que faz a
            // ponte com qualquer outro sistema (federação, cronometragem).
            $table->unsignedInteger('ibge_code')->unique();

            $table->string('name');

            // O mesmo nome sem acento e em minúsculas. Ninguém digita "São
            // Paulo" com til na caixa de busca — sem esta coluna, quem escreve
            // "sao paulo" não acha nada.
            $table->string('name_normalized')->index();

            $table->char('state', 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
