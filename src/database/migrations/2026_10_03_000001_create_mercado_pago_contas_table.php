<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conta Mercado Pago que o organizador conectou pela tela "Cobrança" (OAuth,
 * ADR 0008). Uma por organizador; reconectar substitui.
 *
 * Os tokens vão cifrados com a APP_KEY (cast `encrypted`) — por isso `text`:
 * o texto cifrado é bem maior que o token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mp_user_id', 40);
            $table->string('nome')->nullable();
            $table->string('email')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->string('public_key')->nullable();
            $table->boolean('live_mode')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_contas');
    }
};
