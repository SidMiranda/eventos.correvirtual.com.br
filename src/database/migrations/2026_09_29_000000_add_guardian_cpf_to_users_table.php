<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O CPF do responsável, para quem se cadastra com menos de 18 anos.
 *
 * Menor de idade não responde por si num contrato — e a inscrição é um: tem
 * pagamento, termo de responsabilidade e risco físico. O organizador precisa
 * saber de quem é a assinatura por trás.
 *
 * Nullable porque a maioria é maior de idade e porque quem já tem conta não
 * tem este dado. A obrigatoriedade é condicional, no formulário de cadastro:
 * só é exigido de quem informa nascimento de menos de 18 anos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Só dígitos, como `cpf` — a máscara é coisa da tela.
            $table->string('guardian_cpf', 11)->nullable()->after('cpf');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('guardian_cpf');
        });
    }
};
