<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // O cupom é de UM evento. Sem organizer_id próprio: o vínculo com o
            // organizador passa pelo evento, como em event_kits e
            // event_modalities. Apagar o evento leva os cupons dele junto — um
            // desconto para uma prova que não existe mais não vale nada.
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // 6 ou 7 caracteres, A-Z e 0-9, sempre maiúsculo (ver o mutator em
            // App\Models\Coupon). O tamanho é curto de propósito: o código é
            // digitado à mão, muitas vezes do celular e a partir de um flyer.
            $table->string('code', 7);

            // Anotação do organizador ("parceria com a assessoria X"). Não
            // aparece para o atleta.
            $table->text('description')->nullable();

            // string e não enum: dev roda Postgres e produção roda MySQL (ADR
            // 0005), e enum vira coisa diferente em cada um. O conjunto de
            // valores é garantido pelo model e pela validação.
            $table->string('discount_type', 10); // 'percent' | 'amount'
            $table->decimal('discount_value', 10, 2);

            // Quantos usos o cupom permite, e quantos já foram gastos. O
            // segundo nunca é editável por formulário (ver $fillable do model):
            // quem mexe nele é Coupon::registrarUso().
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('used_quantity')->default(0);

            // Data, não timestamp: o cupom vale até o FIM deste dia.
            $table->date('expires_at');

            $table->boolean('active')->default(true);

            $table->timestamps();

            // Único por evento e não global: unique global acoplaria
            // organizadores diferentes — um bloquearia "CORRE10" para o outro, e
            // o erro contaria que o código existe num lugar que ele não pode
            // ver. Como a busca do cupom sempre parte do evento, não há
            // ambiguidade. Esta chave também serve de índice para event_id,
            // que é a primeira coluna dela.
            $table->unique(['event_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
