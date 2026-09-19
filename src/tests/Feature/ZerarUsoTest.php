<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\EventKit;
use App\Models\EventModality;
use App\Models\Organizer;
use App\Models\Payment;
use App\Models\Sponsor;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Zerar o uso mantendo o catálogo.
 *
 * A linha que este teste protege: inscrição, pagamento, token e contador
 * somem; evento, modalidade, kit, equipe, patrocinador, cupom e usuário ficam
 * exatamente como estavam.
 */
class ZerarUsoTest extends TestCase
{
    use RefreshDatabase;

    private Event $evento;
    private EventKit $kit;
    private EventModality $modalidade;
    private Coupon $cupom;
    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        $organizador = Organizer::factory()->create();
        $this->evento = Event::factory()->create(['organizer_id' => $organizador->id]);
        $this->modalidade = EventModality::factory()->create(['event_id' => $this->evento->id, 'registered_count' => 4]);
        $this->kit = EventKit::factory()->create(['event_id' => $this->evento->id, 'sold' => 4]);
        Team::factory()->create(['organizer_id' => $organizador->id]);
        Sponsor::factory()->create(['organizer_id' => $organizador->id]);
        $this->cupom = Coupon::factory()->create(['event_id' => $this->evento->id, 'used_quantity' => 3]);
        $this->atleta = User::factory()->create();

        $inscricao = Subscription::factory()->create([
            'event_id' => $this->evento->id,
            'user_id' => $this->atleta->id,
            'modality_id' => $this->modalidade->id,
            'kit_id' => $this->kit->id,
            'coupon_id' => $this->cupom->id,
        ]);
        Payment::factory()->create(['subscription_id' => $inscricao->id]);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => $this->atleta->id,
            'name' => 'teste',
            'token' => hash('sha256', 'abc'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $this->atleta->email,
            'token' => 'x',
            'created_at' => now(),
        ]);
    }

    public function test_sem_force_so_lista_e_nao_muda_nada(): void
    {
        $this->artisan('base:zerar-uso')
            ->expectsOutputToContain($this->atleta->email)
            ->expectsOutputToContain('Simulação')
            ->assertSuccessful();

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(3, $this->cupom->fresh()->used_quantity);
    }

    public function test_force_apaga_o_uso_e_zera_os_contadores(): void
    {
        $this->artisan('base:zerar-uso', ['--force' => true])
            ->expectsOutputToContain('Feito.')
            ->assertSuccessful();

        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->assertSame(0, $this->cupom->fresh()->used_quantity);
        $this->assertSame(0, (int) $this->kit->fresh()->sold);
        $this->assertSame(0, (int) $this->modalidade->fresh()->registered_count);
    }

    public function test_force_nao_encosta_no_catalogo_nem_nos_usuarios(): void
    {
        $antes = [
            'users' => 1, 'organizers' => 1, 'events' => 1, 'event_modalities' => 1,
            'event_kits' => 1, 'teams' => 1, 'sponsors' => 1, 'coupons' => 1,
        ];

        $this->artisan('base:zerar-uso', ['--force' => true])->assertSuccessful();

        foreach ($antes as $tabela => $quantidade) {
            $this->assertDatabaseCount($tabela, $quantidade);
        }

        $this->assertDatabaseHas('coupons', ['id' => $this->cupom->id, 'code' => $this->cupom->code, 'active' => true]);
        $this->assertDatabaseHas('users', ['id' => $this->atleta->id, 'email' => $this->atleta->email]);
    }
}
