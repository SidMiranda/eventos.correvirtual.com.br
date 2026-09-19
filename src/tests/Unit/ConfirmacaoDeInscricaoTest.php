<?php

namespace Tests\Unit;

use App\Mail\SubscriptionConfirmed;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ConfirmacaoDeInscricao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A confirmação é uma só, venha do webhook ou do cupom de 100%.
 */
class ConfirmacaoDeInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private function inscricaoPendente(): Subscription
    {
        $evento = Event::factory()->create();
        $modalidade = EventModality::factory()->create(['event_id' => $evento->id]);
        $kit = EventKit::factory()->create(['event_id' => $evento->id]);

        return Subscription::create([
            'event_id' => $evento->id,
            'user_id' => User::factory()->create()->id,
            'modality_id' => $modalidade->id,
            'kit_id' => $kit->id,
            'price' => $kit->price,
            'status' => 'pending',
        ]);
    }

    public function test_confirma_uma_vez_e_preenche_confirmed_at(): void
    {
        Mail::fake();
        $inscricao = $this->inscricaoPendente();

        $this->assertTrue(ConfirmacaoDeInscricao::confirmar($inscricao->id));

        $inscricao->refresh();
        $this->assertSame('paid', $inscricao->status);
        $this->assertNotNull($inscricao->confirmed_at);

        // O e-mail sai depois da resposta; fora de um request, é o terminate()
        // do app que dispara o que ficou agendado.
        $this->app->terminate();

        Mail::assertSent(SubscriptionConfirmed::class, fn ($mail) => $mail->hasTo($inscricao->user->email));
    }

    public function test_segunda_chamada_nao_confirma_de_novo_nem_reenvia_email(): void
    {
        Mail::fake();
        $inscricao = $this->inscricaoPendente();

        $this->assertTrue(ConfirmacaoDeInscricao::confirmar($inscricao->id));
        $primeiraConfirmacao = $inscricao->fresh()->confirmed_at;

        $this->assertFalse(ConfirmacaoDeInscricao::confirmar($inscricao->id));

        $this->app->terminate();

        $this->assertEquals($primeiraConfirmacao, $inscricao->fresh()->confirmed_at);
        Mail::assertSentCount(1);
    }

    public function test_inscricao_inexistente_nao_confirma_nada(): void
    {
        Mail::fake();

        $this->assertFalse(ConfirmacaoDeInscricao::confirmar(999));

        $this->app->terminate();

        Mail::assertNothingSent();
    }
}
