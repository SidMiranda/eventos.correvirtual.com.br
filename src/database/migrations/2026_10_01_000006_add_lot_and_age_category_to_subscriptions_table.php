<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O retrato financeiro da inscrição ganha o lote e a categoria etária.
 *
 * `discount_amount` continua sendo SÓ o cupom. O desconto de idade tem coluna
 * própria: um balaio de descontos numa coluna só inviabilizaria o financeiro
 * que vier (ADR 0007). O total continua em `price`.
 *
 * Inscrição anterior a esta migration fica com lote e categoria nulos e o
 * desconto de idade zero — o retrato dela já estava completo nas colunas de
 * valor, e não se reescreve histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // nullOnDelete: o retrato está nas colunas de valor, não no
            // vínculo. Apagar um lote nunca pode apagar inscrição.
            $table->foreignId('lot_id')->nullable()->after('coupon_id')
                ->constrained('event_lots')->nullOnDelete();

            $table->foreignId('age_category_id')->nullable()->after('lot_id')
                ->constrained('age_categories')->nullOnDelete();

            $table->decimal('age_discount_amount', 8, 2)->default(0)->after('age_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lot_id');
            $table->dropConstrainedForeignId('age_category_id');
            $table->dropColumn('age_discount_amount');
        });
    }
};
