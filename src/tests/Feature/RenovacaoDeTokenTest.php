<?php

namespace Tests\Feature;

use App\Models\MercadoPagoConta;
use App\Models\Organizer;
use App\Services\Cobranca\ConfiguracaoDaPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Renovação do token OAuth (vale 180 dias) e a taxa configurável (ADR 0008).
 */
class RenovacaoDeTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.app.client_id' => 'id-do-app',
            'services.mercadopago.app.client_secret' => 'segredo-do-app',
            'services.mercadopago.app.redirect_uri' => 'https://x/retorno',
        ]);
    }

    private function conta(int $diasParaVencer, array $extra = []): MercadoPagoConta
    {
        return MercadoPagoConta::create(array_merge([
            'organizer_id' => Organizer::factory()->create()->id,
            'mp_user_id' => '1',
            'access_token' => 'token-velho',
            'refresh_token' => 'refresh-velho',
            'expires_at' => now()->addDays($diasParaVencer),
        ], $extra));
    }

    public function test_renova_quem_vence_em_ate_30_dias_e_deixa_os_outros(): void
    {
        Http::fake(['api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'token-novo', 'refresh_token' => 'refresh-novo', 'expires_in' => 15552000, 'user_id' => 1,
        ])]);

        $vencendo = $this->conta(20);
        $longe = $this->conta(120);

        $this->artisan('mercadopago:renovar-tokens')->assertSuccessful();

        $this->assertSame('token-novo', $vencendo->fresh()->access_token);
        $this->assertSame('refresh-novo', $vencendo->fresh()->refresh_token);
        $this->assertNotNull($vencendo->fresh()->refreshed_at);
        $this->assertSame('token-velho', $longe->fresh()->access_token);

        Http::assertSent(fn ($r) => $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'refresh-velho'
            && $r['client_secret'] === 'segredo-do-app');
    }

    public function test_sem_refresh_token_novo_mantem_o_antigo(): void
    {
        Http::fake(['api.mercadopago.com/oauth/token' => Http::response([
            'access_token' => 'token-novo', 'expires_in' => 15552000, 'user_id' => 1,
        ])]);

        $conta = $this->conta(5);
        $this->artisan('mercadopago:renovar-tokens')->assertSuccessful();

        $this->assertSame('refresh-velho', $conta->fresh()->refresh_token);
    }

    public function test_falha_grava_o_erro_mantem_o_token_e_alerta_por_email(): void
    {
        Mail::fake();
        config(['services.alertas.cobranca_email' => 'alerta@exemplo.com']);
        Http::fake(['api.mercadopago.com/oauth/token' => Http::response(['message' => 'invalid_grant'], 400)]);
        Log::spy();

        $conta = $this->conta(5);

        $this->artisan('mercadopago:renovar-tokens')->assertFailed();

        $conta->refresh();
        $this->assertSame('token-velho', $conta->access_token);
        $this->assertStringContainsString('invalid_grant', $conta->last_error);
        Log::shouldHaveReceived('critical')->withArgs(fn ($msg) => str_contains($msg, 'Falha ao renovar o token'))->once();
        // Nem o client_secret nem o refresh_token vão para o erro gravado.
        $this->assertStringNotContainsString('segredo-do-app', $conta->last_error);
        $this->assertStringNotContainsString('refresh-velho', $conta->last_error);
    }

    public function test_o_mesmo_alerta_nao_repete_dentro_da_hora(): void
    {
        config(['services.alertas.cobranca_email' => 'alerta@exemplo.com', 'mail.default' => 'array']);
        Http::fake(['api.mercadopago.com/oauth/token' => Http::response(['message' => 'x'], 500)]);

        $this->conta(5);
        $this->artisan('mercadopago:renovar-tokens');
        $this->artisan('mercadopago:renovar-tokens');

        // Conta os e-mails que de fato saíram pelo transporte `array`.
        $mensagens = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $mensagens);
        $this->assertStringContainsString('[URGENTE] Cobrança', $mensagens[0]->getOriginalMessage()->getSubject());
    }

    public function test_o_comando_da_taxa_mostra_e_muda_o_valor(): void
    {
        $this->artisan('plataforma:taxa')->expectsOutput('Taxa atual: R$ 0,70')->assertSuccessful();

        $this->artisan('plataforma:taxa', ['valor' => '0,85'])->assertSuccessful();
        $this->assertSame(0.85, ConfiguracaoDaPlataforma::taxaDeInscricao());

        $this->artisan('plataforma:taxa', ['valor' => 'abc'])->assertFailed();
        $this->artisan('plataforma:taxa', ['valor' => '-1'])->assertFailed();
        $this->assertSame(0.85, ConfiguracaoDaPlataforma::taxaDeInscricao());
    }

    public function test_a_renovacao_esta_agendada(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('mercadopago:renovar-tokens')->assertSuccessful();
    }
}
