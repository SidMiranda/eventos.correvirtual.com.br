<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();

            // A foto é do ORGANIZADOR, como equipe e patrocinador: a home é
            // dele, e a galeria não pertence a uma prova. "photos" e não
            // "gallery": config/galeria.php e GaleriaDeRealizados já existem
            // e são a vitrine de provas realizadas — outra coisa.
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();

            // Legenda curta. Não aparece na faixa da home (é um feed, só foto);
            // vira o alt e o title da imagem.
            $table->string('caption')->nullable();

            // Opcional: o post no Instagram, por exemplo. Precisa de esquema
            // (https://) — sem ele viraria link relativo, como no patrocinador.
            $table->string('link_url')->nullable();

            // Ordem na faixa: menor primeiro; empate, a mais nova primeiro.
            $table->integer('position')->default(0);

            $table->boolean('active')->default(true);

            $table->timestamps();

            // Sem has_image: linha existe => imagem existe. O painel cria a
            // linha, grava a derivada e, se a gravação falhar, apaga a linha.
            $table->index(['organizer_id', 'active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
