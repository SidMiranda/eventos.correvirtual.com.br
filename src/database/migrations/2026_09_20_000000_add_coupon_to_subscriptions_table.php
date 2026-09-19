<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O retrato financeiro da inscrição.
 *
 * A inscrição passa a guardar o que foi cobrado E como se chegou nesse valor:
 * preço do kit na hora, desconto e cupom. Um relatório financeiro futuro lê só
 * esta tabela — sem reconstruir nada a partir do kit (que muda de preço) ou do
 * cupom (que é editável). Ver docs/specs/cupons-de-desconto.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // O preço do kit no momento da inscrição. `price` continua sendo o
            // que foi cobrado de verdade — nada que lê `price` hoje muda.
            //
            // Nullable no schema mas sempre preenchido: o model copia de
            // `price` quando não vem (ver Subscription::booted). NOT NULL
            // exigiria `change()`, que se comporta diferente em SQLite (testes),
            // Postgres (dev) e MySQL (produção).
            $table->decimal('list_price', 8, 2)->nullable()->after('kit_id');

            $table->decimal('discount_amount', 8, 2)->default(0)->after('list_price');

            // nullOnDelete e não restrictOnDelete: o retrato financeiro está
            // nas colunas de valor, não no vínculo. Um RESTRICT aqui criaria um
            // modo de falha novo no cascade event → coupons, e a trava contra
            // apagar cupom usado já vive no controller do painel.
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('discount_amount')
                ->constrained()
                ->nullOnDelete();
        });

        // Nenhuma inscrição anterior a esta migration teve desconto: o preço
        // de tabela dela é o próprio valor cobrado.
        DB::table('subscriptions')
            ->whereNull('list_price')
            ->update(['list_price' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['list_price', 'discount_amount']);
        });
    }
};
