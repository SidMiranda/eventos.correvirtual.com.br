<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O retrato da cobrança no pagamento (ADR 0008): por qual conta ela saiu
 * (nulo = modelo antigo, credencial do .env) e a taxa da plataforma enviada.
 * O webhook consulta o pagamento com a mesma conta que o criou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('mercado_pago_conta_id')->nullable()->after('provider')
                ->constrained('mercado_pago_contas')->nullOnDelete();
            $table->decimal('application_fee', 10, 2)->nullable()->after('mercado_pago_conta_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mercado_pago_conta_id');
            $table->dropColumn('application_fee');
        });
    }
};
