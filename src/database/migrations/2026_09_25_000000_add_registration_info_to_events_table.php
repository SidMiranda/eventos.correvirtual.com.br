<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O texto do bloco "Inscrição" da página do evento.
 *
 * Mesmo caso do cronograma (ver a migration ao lado): a frase "A inscrição dá
 * direito ao kit exclusivo do evento" estava chumbada no Blade e aparecia
 * igual em toda prova. É ali que o organizador precisa explicar o que a
 * inscrição inclui, o que fazer na retirada do kit, a política de troca de
 * tamanho — coisas que mudam de evento para evento.
 *
 * A linha "Encerramento das inscrições" não entra aqui: ela é calculada de
 * `registration_deadline` e continua saindo sozinha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('registration_info')->nullable()->after('schedule');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('registration_info');
        });
    }
};
