<?php

namespace Tests\Feature\Admin;

use App\Models\MercadoPagoConta;
use App\Models\Organizer;
use App\Models\User;
use App\Services\Cobranca\ConfiguracaoDaPlataforma;
use App\Services\Cobranca\EstadoDoOAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Cobrança": o organizador conecta a conta Mercado Pago por OAuth (ADR 0008,
 * docs/specs/cobranca-split-mercado-pago.md).
 */
class CobrancaConectarTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizador;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizador = Organizer::factory()->create();
        $this->admin = User::factory()->create(['role' => 'organizer_admin', 'organizer_id' => $this->organizador->id]);

        config([
            'services.mercadopago.app.client_id' => '1234567890',
            'services.mercadopago.app.client_secret' => 'segredo-do-app',
            'services.mercadopago.app.redirect_uri' => 'https://admin.exemplo.com/mercadopago/oauth/retorno',
        ]);
    }

    private function fingirMercadoPago(int $status = 200): void
    {
        Http::fake([
            'api.mercadopago.com/oauth/token' => Http::response($status === 200 ? [
                'access_token' => 'APP_USR-token-do-organizador',
                'refresh_token' => 'TG-refresh-do-organizador',
                'expires_in' => 15552000,
                'user_id' => 998877,
                'public_key' => 'APP_USR-public',
                'live_mode' => true,
            ] : ['message' => 'invalid_grant'], $status),
            'api.mercadopago.com/users/me' => Http::response([
                'first_name' => 'Ueslei',
                'last_name' => 'Azarias',
                'email' => 'ueslei@exemplo.com',
                'nickname' => 'UESLEI123',
            ]),
        ]);
    }

    private function state(?int $organizador = null, ?int $usuario = null): string
    {
        return EstadoDoOAuth::gerar($organizador ?? $this->organizador->id, $usuario ?? $this->admin->id, 'https://eventos.exemplo.com');
    }

    /*
    |--------------------------------------------------------------------------
    | A tela
    |--------------------------------------------------------------------------
    */

    public function test_o_menu_tem_cobranca_por_ultimo(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/cobranca')->assertOk()->getContent();

        $this->assertStringContainsString('data-feather="dollar-sign"', $html);
        $this->assertGreaterThan(strpos($html, 'Sobre nós'), strpos($html, 'nav-link nav-icone-cobranca'));
    }

    public function test_sem_conta_oferece_conectar(): void
    {
        $this->actingAs($this->admin)->get('/admin/cobranca')
            ->assertOk()
            ->assertSee('Conectar conta do Mercado Pago')
            ->assertDontSee('Sua conta está conectada');
    }

    public function test_sem_a_aplicacao_configurada_explica_em_vez_de_quebrar(): void
    {
        config(['services.mercadopago.app.client_id' => null]);

        $this->actingAs($this->admin)->get('/admin/cobranca')
            ->assertOk()
            ->assertDontSee('Conectar conta do Mercado Pago')
            ->assertSee('está sendo configurada');

        $this->actingAs($this->admin)->post('/admin/cobranca/conectar')->assertStatus(503);
    }

    public function test_a_tela_mostra_as_taxas_da_configuracao_e_o_exemplo(): void
    {
        $this->actingAs($this->admin)->get('/admin/cobranca')
            ->assertOk()
            ->assertSee('01/01/2027')
            ->assertSee('R$ 0,70')
            ->assertSee('0,99%')
            ->assertSee('R$ 98,31');

        ConfiguracaoDaPlataforma::definir(ConfiguracaoDaPlataforma::TAXA_INSCRICAO, '0.80');

        $this->actingAs($this->admin)->get('/admin/cobranca')
            ->assertSee('R$ 0,80')
            ->assertSee('R$ 98,21');
    }

    public function test_conectada_a_tela_diz_em_que_conta_recebe(): void
    {
        MercadoPagoConta::create([
            'organizer_id' => $this->organizador->id,
            'mp_user_id' => '1',
            'nome' => 'Ueslei Azarias',
            'email' => 'ueslei@exemplo.com',
            'access_token' => 'x',
            'connected_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/admin/cobranca')
            ->assertOk()
            ->assertSee('Sua conta está conectada')
            ->assertSee('Ueslei Azarias (ueslei@exemplo.com)')
            ->assertDontSee('Conectar conta do Mercado Pago');
    }

    public function test_atleta_e_deslogado_nao_entram(): void
    {
        $this->get('/admin/cobranca')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'athlete']))->get('/admin/cobranca')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Conectar
    |--------------------------------------------------------------------------
    */

    public function test_conectar_leva_ao_mercado_pago_com_state_cifrado(): void
    {
        $resposta = $this->actingAs($this->admin)->post('/admin/cobranca/conectar');

        $url = $resposta->headers->get('Location');
        $this->assertStringStartsWith('https://auth.mercadopago.com/authorization?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('1234567890', $q['client_id']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('https://admin.exemplo.com/mercadopago/oauth/retorno', $q['redirect_uri']);
        // O state não entrega nada em claro.
        $this->assertStringNotContainsString((string) $this->organizador->id . ',', $q['state']);
        $this->assertNotNull(EstadoDoOAuth::conferir($q['state']));
    }

    public function test_retorno_grava_a_conta_do_organizador_certo_com_tokens_cifrados(): void
    {
        $this->fingirMercadoPago();

        $this->get('/mercadopago/oauth/retorno?code=TG-codigo&state=' . urlencode($this->state()))
            ->assertRedirect('https://eventos.exemplo.com/admin/cobranca?mp=conectado');

        $conta = MercadoPagoConta::where('organizer_id', $this->organizador->id)->firstOrFail();
        $this->assertSame('998877', $conta->mp_user_id);
        $this->assertSame('Ueslei Azarias', $conta->nome);
        $this->assertSame('APP_USR-token-do-organizador', $conta->access_token);
        $this->assertSame('TG-refresh-do-organizador', $conta->refresh_token);
        $this->assertTrue($conta->expires_at->between(now()->addDays(179), now()->addDays(181)));

        // No banco, cifrado.
        $cru = DB::table('mercado_pago_contas')->value('access_token');
        $this->assertStringNotContainsString('APP_USR-token-do-organizador', $cru);
        $this->assertSame('APP_USR-token-do-organizador', Crypt::decryptString($cru));

        // A troca levou o client_secret da aplicação e o mesmo redirect_uri.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/token')
            && $r['grant_type'] === 'authorization_code'
            && $r['code'] === 'TG-codigo'
            && $r['client_secret'] === 'segredo-do-app'
            && $r['redirect_uri'] === 'https://admin.exemplo.com/mercadopago/oauth/retorno');
    }

    public function test_reconectar_substitui_a_conta(): void
    {
        $this->fingirMercadoPago();
        $this->get('/mercadopago/oauth/retorno?code=a&state=' . urlencode($this->state()));
        $this->get('/mercadopago/oauth/retorno?code=b&state=' . urlencode($this->state()));

        $this->assertSame(1, MercadoPagoConta::where('organizer_id', $this->organizador->id)->count());
    }

    public function test_state_adulterado_vencido_ou_repetido_nao_grava_nada(): void
    {
        $this->fingirMercadoPago();

        $this->get('/mercadopago/oauth/retorno?code=x&state=lixo')->assertStatus(400);

        $state = $this->state();
        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($state))->assertRedirect();
        MercadoPagoConta::query()->delete();
        // O mesmo state de novo: uso único.
        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($state))->assertStatus(400);

        $vencido = $this->state();
        $this->travel(16)->minutes();
        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($vencido))->assertStatus(400);

        $this->assertSame(0, MercadoPagoConta::count());
    }

    public function test_quem_perdeu_o_acesso_nao_conecta(): void
    {
        $this->fingirMercadoPago();
        $state = $this->state();
        $this->admin->update(['role' => 'athlete']);

        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($state))
            ->assertRedirect('https://eventos.exemplo.com/admin/cobranca?mp=erro');

        $this->assertSame(0, MercadoPagoConta::count());
    }

    public function test_state_de_um_organizador_nao_grava_em_outro(): void
    {
        // Admin do organizador A com um state que diz "organizador B": o
        // usuário não administra B, então nada é gravado.
        $outro = Organizer::factory()->create();
        $this->fingirMercadoPago();

        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($this->state($outro->id)))
            ->assertRedirect('https://eventos.exemplo.com/admin/cobranca?mp=erro');

        $this->assertSame(0, MercadoPagoConta::count());
    }

    public function test_cancelar_no_mercado_pago_volta_sem_gravar(): void
    {
        $this->fingirMercadoPago();

        $this->get('/mercadopago/oauth/retorno?error=access_denied&state=' . urlencode($this->state()))
            ->assertRedirect('https://eventos.exemplo.com/admin/cobranca?mp=cancelado');

        $this->assertSame(0, MercadoPagoConta::count());
    }

    public function test_mercado_pago_recusando_o_codigo_volta_com_erro(): void
    {
        $this->fingirMercadoPago(400);

        $this->get('/mercadopago/oauth/retorno?code=x&state=' . urlencode($this->state()))
            ->assertRedirect('https://eventos.exemplo.com/admin/cobranca?mp=erro');

        $this->assertSame(0, MercadoPagoConta::count());
    }

    public function test_os_tokens_nao_vazam_no_json_do_model(): void
    {
        $conta = MercadoPagoConta::create([
            'organizer_id' => $this->organizador->id,
            'mp_user_id' => '1',
            'access_token' => 'segredo',
            'refresh_token' => 'segredo2',
        ]);

        $this->assertStringNotContainsString('segredo', $conta->toJson());
    }
}
