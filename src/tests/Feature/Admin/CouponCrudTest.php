<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A gestão de cupons no painel.
 *
 * Duas famílias de garantia aqui: o isolamento entre organizadores (um não
 * enxerga nem mexe no cupom do outro, nem informando o id na URL) e as travas
 * que passam a valer depois do primeiro uso — que precisam viver no servidor,
 * não só na tela.
 *
 * Ver docs/specs/cupons-de-desconto.md.
 */
class CouponCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;
    private Event $eventoA;
    private Event $eventoB;

    protected function setUp(): void
    {
        parent::setUp();

        // IdentifyOrganizerByDomain roda em toda request e resolve o organizador
        // pelo host default dos testes ("localhost").
        Organizer::factory()->create(['domain' => 'localhost']);

        $this->organizadorA = Organizer::factory()->create();
        $this->organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);

        $this->eventoA = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);
        $this->eventoB = Event::factory()->create(['organizer_id' => $this->organizadorB->id]);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'event_id' => $this->eventoA->id,
            'code' => 'CORRE10',
            'description' => 'Parceria com a assessoria do Zé',
            'discount_type' => Coupon::TIPO_PERCENTUAL,
            'discount_value' => 10,
            'total_quantity' => 50,
            'expires_at' => now()->addDays(10)->toDateString(),
            'active' => 1,
        ], $extra);
    }

    private function cupomDoA(array $extra = []): Coupon
    {
        return Coupon::factory()->create(array_merge(['event_id' => $this->eventoA->id], $extra));
    }

    private function cupomDoB(array $extra = []): Coupon
    {
        return Coupon::factory()->create(array_merge(['event_id' => $this->eventoB->id], $extra));
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso
    |--------------------------------------------------------------------------
    */

    public function test_deslogado_vai_para_o_login_e_atleta_leva_403(): void
    {
        $this->get('/admin/cupons')->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/cupons')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Criação
    |--------------------------------------------------------------------------
    */

    public function test_cria_cupom_normalizando_o_codigo(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['code' => '  corre10 ']))
            ->assertRedirect('/admin/cupons');

        $cupom = Coupon::first();

        $this->assertNotNull($cupom);
        $this->assertSame('CORRE10', $cupom->code);
        $this->assertSame($this->eventoA->id, $cupom->event_id);
        $this->assertSame(0, $cupom->used_quantity);
        $this->assertTrue($cupom->active);
    }

    public function test_quantidade_utilizada_vinda_do_formulario_e_ignorada(): void
    {
        // O contador de uso não é campo de formulário. Se fosse, bastaria um
        // POST montado na mão para "devolver" usos já gastos.
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['used_quantity' => 99]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame(0, Coupon::first()->used_quantity);
    }

    public function test_recusa_codigo_fora_do_formato(): void
    {
        foreach (['CURTO', 'LONGODEMAIS', 'CORRE-1', 'CORRE 1', 'CÓDIG0'] as $codigo) {
            $this->actingAs($this->adminA)
                ->post('/admin/cupons', $this->dados(['code' => $codigo]))
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(0, Coupon::count());
    }

    public function test_recusa_codigo_repetido_no_mesmo_evento(): void
    {
        $this->cupomDoA(['code' => 'CORRE10']);

        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['code' => 'corre10']))
            ->assertSessionHasErrors('code');

        $this->assertSame(1, Coupon::where('code', 'CORRE10')->count());
    }

    public function test_aceita_o_mesmo_codigo_em_outro_evento(): void
    {
        // O código é único POR EVENTO, não global: unique global acoplaria
        // organizadores diferentes (ver a migration).
        $outroEvento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);
        $this->cupomDoA(['code' => 'CORRE10']);

        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['code' => 'CORRE10', 'event_id' => $outroEvento->id]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame(2, Coupon::where('code', 'CORRE10')->count());
    }

    public function test_recusa_percentual_fora_da_faixa(): void
    {
        foreach ([0, 101] as $valor) {
            $this->actingAs($this->adminA)
                ->post('/admin/cupons', $this->dados(['discount_value' => $valor]))
                ->assertSessionHasErrors('discount_value');
        }

        $this->assertSame(0, Coupon::count());
    }

    public function test_recusa_desconto_em_reais_zerado(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados([
                'discount_type' => Coupon::TIPO_VALOR,
                'discount_value' => 0,
            ]))
            ->assertSessionHasErrors('discount_value');
    }

    public function test_aceita_desconto_em_reais(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados([
                'discount_type' => Coupon::TIPO_VALOR,
                'discount_value' => 25.50,
            ]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame('25.50', Coupon::first()->discount_value);
    }

    public function test_recusa_quantidade_zerada(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['total_quantity' => 0]))
            ->assertSessionHasErrors('total_quantity');
    }

    public function test_recusa_data_no_passado(): void
    {
        // Um cupom que já nasce vencido não serve para nada.
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['expires_at' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('expires_at');
    }

    public function test_aceita_cupom_que_vence_hoje(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['expires_at' => today()->toDateString()]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame(1, Coupon::count());
    }

    public function test_nao_cria_cupom_em_evento_de_outro_organizador(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['event_id' => $this->eventoB->id]))
            ->assertSessionHasErrors('event_id');

        $this->assertSame(0, Coupon::count());
    }

    public function test_nao_cria_cupom_em_evento_ja_realizado(): void
    {
        // O <select> da tela não lista prova passada, mas o valor vem do
        // navegador: trocá-lo na mão não pode abrir a porta que a tela fechou.
        $realizado = Event::factory()->create([
            'organizer_id' => $this->organizadorA->id,
            'event_date' => now()->subMonth(),
        ]);

        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['event_id' => $realizado->id]))
            ->assertSessionHasErrors('event_id');
    }

    public function test_o_form_do_modal_cria_e_edita_pelo_mesmo_caminho(): void
    {
        // O modal e um <form> so, que alterna entre criar e editar trocando o
        // _method no corpo. E o que o navegador envia de verdade, entao e assim
        // que precisa ser testado: _method vazio ou ausente aqui daria
        // SuspiciousOperationException no Symfony.
        $this->actingAs($this->adminA)
            ->post('/admin/cupons', $this->dados(['_method' => 'POST']))
            ->assertRedirect('/admin/cupons');

        $cupom = Coupon::first();

        $this->actingAs($this->adminA)
            ->post("/admin/cupons/{$cupom->id}", $this->dados([
                '_method' => 'PUT',
                'code' => 'NOVO123',
            ]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame('NOVO123', $cupom->fresh()->code);
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento entre organizadores
    |--------------------------------------------------------------------------
    */

    public function test_listagem_mostra_so_os_cupons_do_proprio_organizador(): void
    {
        $meu = $this->cupomDoA(['code' => 'MEUCUP']);
        $alheio = $this->cupomDoB(['code' => 'ALHEIO']);

        $this->actingAs($this->adminA)
            ->get('/admin/cupons')
            ->assertOk()
            ->assertSee($meu->code)
            ->assertDontSee($alheio->code);
    }

    public function test_nao_edita_apaga_nem_liga_cupom_de_outro_organizador(): void
    {
        $alheio = $this->cupomDoB(['code' => 'ALHEIO', 'active' => true]);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$alheio->id}", $this->dados())
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->delete("/admin/cupons/{$alheio->id}")
            ->assertNotFound();

        $this->actingAs($this->adminA)
            ->patch("/admin/cupons/{$alheio->id}/status")
            ->assertNotFound();

        // E o cupom do vizinho continua exatamente como estava.
        $alheio->refresh();
        $this->assertSame('ALHEIO', $alheio->code);
        $this->assertSame($this->eventoB->id, $alheio->event_id);
        $this->assertTrue($alheio->active);
    }

    public function test_nao_move_cupom_proprio_para_evento_de_outro_organizador(): void
    {
        $cupom = $this->cupomDoA();

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", $this->dados([
                'code' => $cupom->code,
                'event_id' => $this->eventoB->id,
            ]))
            ->assertSessionHasErrors('event_id');

        $this->assertSame($this->eventoA->id, $cupom->fresh()->event_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Edição
    |--------------------------------------------------------------------------
    */

    public function test_edita_cupom_sem_uso(): void
    {
        $cupom = $this->cupomDoA(['code' => 'CORRE10']);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", $this->dados([
                'code' => 'NOVO123',
                'discount_value' => 20,
                'total_quantity' => 80,
            ]))
            ->assertRedirect('/admin/cupons');

        $cupom->refresh();
        $this->assertSame('NOVO123', $cupom->code);
        $this->assertSame('20.00', $cupom->discount_value);
        $this->assertSame(80, $cupom->total_quantity);
    }

    public function test_cupom_usado_nao_troca_de_codigo_nem_de_evento(): void
    {
        $outroEvento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);
        $cupom = $this->cupomDoA(['code' => 'CORRE10', 'used_quantity' => 3]);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", $this->dados([
                'code' => 'OUTRO12',
                'event_id' => $outroEvento->id,
            ]))
            ->assertSessionHasErrors('code');

        $cupom->refresh();
        $this->assertSame('CORRE10', $cupom->code);
        $this->assertSame($this->eventoA->id, $cupom->event_id);
    }

    public function test_cupom_usado_continua_editavel_no_resto(): void
    {
        // É o que o formulário manda de verdade: o código vai igual (o campo é
        // readonly) e o evento não vem (o select é disabled).
        $cupom = $this->cupomDoA(['code' => 'CORRE10', 'used_quantity' => 3]);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", [
                'code' => 'CORRE10',
                'description' => 'Prorrogado até o fim do mês',
                'discount_type' => Coupon::TIPO_VALOR,
                'discount_value' => 15,
                'total_quantity' => 80,
                'expires_at' => now()->addDays(30)->toDateString(),
                'active' => 1,
            ])
            ->assertRedirect('/admin/cupons');

        $cupom->refresh();
        $this->assertSame('CORRE10', $cupom->code);
        $this->assertSame(Coupon::TIPO_VALOR, $cupom->discount_type);
        $this->assertSame(80, $cupom->total_quantity);
        $this->assertSame(3, $cupom->used_quantity);
        $this->assertSame('Prorrogado até o fim do mês', $cupom->description);
    }

    public function test_quantidade_nao_desce_abaixo_do_que_ja_foi_usado(): void
    {
        $cupom = $this->cupomDoA(['code' => 'CORRE10', 'used_quantity' => 5]);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", $this->dados([
                'code' => 'CORRE10',
                'total_quantity' => 2,
            ]))
            ->assertSessionHasErrors('total_quantity');

        $this->assertSame(10, $cupom->fresh()->total_quantity);
    }

    public function test_cupom_vencido_pode_ser_editado_sem_mudar_a_data(): void
    {
        // Corrigir a descrição de um cupom vencido não pode exigir inventar
        // uma validade nova para ele.
        $cupom = $this->cupomDoA(['code' => 'CORRE10', 'expires_at' => now()->subDays(5)->toDateString()]);

        $this->actingAs($this->adminA)
            ->put("/admin/cupons/{$cupom->id}", $this->dados([
                'code' => 'CORRE10',
                'description' => 'Campanha encerrada',
                'expires_at' => $cupom->expires_at->toDateString(),
            ]))
            ->assertRedirect('/admin/cupons');

        $this->assertSame('Campanha encerrada', $cupom->fresh()->description);
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusão
    |--------------------------------------------------------------------------
    */

    public function test_apaga_cupom_sem_uso(): void
    {
        $cupom = $this->cupomDoA();

        $this->actingAs($this->adminA)
            ->delete("/admin/cupons/{$cupom->id}")
            ->assertRedirect('/admin/cupons');

        $this->assertNull(Coupon::find($cupom->id));
    }

    public function test_nao_apaga_cupom_que_ja_teve_uso(): void
    {
        $cupom = $this->cupomDoA(['used_quantity' => 1]);

        $this->actingAs($this->adminA)
            ->delete("/admin/cupons/{$cupom->id}")
            ->assertSessionHasErrors('cupom');

        $this->assertNotNull(Coupon::find($cupom->id));
    }

    /*
    |--------------------------------------------------------------------------
    | Ativar e inativar
    |--------------------------------------------------------------------------
    */

    public function test_toggle_liga_e_desliga(): void
    {
        $cupom = $this->cupomDoA(['active' => true]);

        $this->actingAs($this->adminA)
            ->patch("/admin/cupons/{$cupom->id}/status")
            ->assertRedirect();

        $this->assertFalse($cupom->fresh()->active);

        $this->actingAs($this->adminA)->patch("/admin/cupons/{$cupom->id}/status");

        $this->assertTrue($cupom->fresh()->active);
    }

    public function test_toggle_continua_valendo_para_cupom_com_uso(): void
    {
        // Ter sido usado não congela o liga/desliga — congela só código e evento.
        $cupom = $this->cupomDoA(['active' => true, 'used_quantity' => 4]);

        $this->actingAs($this->adminA)->patch("/admin/cupons/{$cupom->id}/status");

        $this->assertFalse($cupom->fresh()->active);
    }

    public function test_toggle_recusa_cupom_esgotado(): void
    {
        $cupom = $this->cupomDoA(['active' => false, 'total_quantity' => 3, 'used_quantity' => 3]);

        $this->actingAs($this->adminA)
            ->patch("/admin/cupons/{$cupom->id}/status")
            ->assertSessionHasErrors('cupom');

        $this->assertFalse($cupom->fresh()->active);
    }

    public function test_toggle_recusa_cupom_vencido(): void
    {
        $cupom = $this->cupomDoA(['active' => true, 'expires_at' => now()->subDay()->toDateString()]);

        $this->actingAs($this->adminA)
            ->patch("/admin/cupons/{$cupom->id}/status")
            ->assertSessionHasErrors('cupom');

        // Continua como estava: encerrado já lê como inativo na tela.
        $this->assertTrue($cupom->fresh()->active);
    }

    /*
    |--------------------------------------------------------------------------
    | Estados vazios
    |--------------------------------------------------------------------------
    */

    public function test_sem_nenhum_cupom_a_tela_convida_a_criar_o_primeiro(): void
    {
        $this->actingAs($this->adminA)
            ->get('/admin/cupons')
            ->assertOk()
            ->assertSee('Nenhum cupom criado ainda.')
            ->assertSee('Criar o primeiro cupom');
    }

    public function test_sem_evento_futuro_a_tela_manda_criar_um_evento(): void
    {
        // Um formulario de cupom sem evento nenhum para escolher seria um beco
        // sem saida. Mesma conversa do seletor de modalidades e kits.
        $this->eventoA->update(['event_date' => now()->subMonth()]);

        $this->actingAs($this->adminA)
            ->get('/admin/cupons')
            ->assertOk()
            ->assertSee('Nenhum evento futuro para criar cupom.')
            ->assertDontSee('Criar o primeiro cupom');
    }

    /*
    |--------------------------------------------------------------------------
    | Filtro e busca
    |--------------------------------------------------------------------------
    */

    public function test_filtra_por_evento(): void
    {
        $outroEvento = Event::factory()->create(['organizer_id' => $this->organizadorA->id]);

        $daqui = $this->cupomDoA(['code' => 'DAQUI1']);
        $dali = Coupon::factory()->create(['event_id' => $outroEvento->id, 'code' => 'DALI12']);

        $this->actingAs($this->adminA)
            ->get('/admin/cupons?evento=' . $this->eventoA->id)
            ->assertOk()
            ->assertSee($daqui->code)
            ->assertDontSee($dali->code);
    }

    public function test_busca_por_codigo_ignora_caixa(): void
    {
        $achado = $this->cupomDoA(['code' => 'VERAO25']);
        $outro = $this->cupomDoA(['code' => 'NATAL1']);

        $this->actingAs($this->adminA)
            ->get('/admin/cupons?busca=verao')
            ->assertOk()
            ->assertSee($achado->code)
            ->assertDontSee($outro->code);
    }
}
