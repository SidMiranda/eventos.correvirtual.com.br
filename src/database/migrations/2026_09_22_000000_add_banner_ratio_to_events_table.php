<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "O banner enviado serve para o topo da página?"
 *
 * O campo de banner do painel sempre aceitou qualquer imagem, e na prática
 * recebeu duas coisas diferentes: o cartaz da prova (retrato, 1080x1920) e um
 * banner de verdade (horizontal, 1600x320). Cartaz retrato num quadro largo
 * fica péssimo — foi por isso que em 2026-08-30 o topo virou um degradê fixo
 * e a imagem deixou de ser usada em lugar nenhum.
 *
 * Esta coluna devolve a imagem ao topo sem trazer o problema de volta: quem
 * mandou banner largo vê o banner; quem mandou o cartaz continua no degradê.
 * O valor é medido no upload (ver ImagensDoEvento::salvarBanner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // A proporção da imagem (largura ÷ altura), medida no upload.
            // Guardar o número, e não um "é largo?", deixa a página desenhar o
            // quadro na proporção exata do banner — sem cortar as pontas, que
            // é justo onde ficam as logos de quem patrocina.
            $table->decimal('banner_ratio', 5, 3)->nullable()->after('banner_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('banner_ratio');
        });
    }
};
