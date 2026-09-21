<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A troca de senha de quem já está logado.
 *
 * O item "Alterar senha" do menu apontava para `#!` desde sempre: o link
 * existia, a tela não. Estes testes cobrem a tela que faltava — em especial a
 * exigência da senha atual, que é o que impede alguém numa sessão aberta de
 * tomar a conta.
 */
class AlterarSenhaTest extends TestCase
{
    use RefreshDatabase;

    private User $atleta;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);

        $this->atleta = User::factory()->create([
            'role' => 'athlete',
            'password' => Hash::make('senha-atual-123'),
        ]);
    }

    private function trocar(array $dados)
    {
        return $this->actingAs($this->atleta)->put('/alterar-senha', $dados);
    }

    public function test_deslogado_e_mandado_para_o_login(): void
    {
        $this->get('/alterar-senha')->assertRedirect('/login');
        $this->put('/alterar-senha', [])->assertRedirect('/login');
    }

    public function test_logado_abre_a_tela(): void
    {
        $this->actingAs($this->atleta)
            ->get('/alterar-senha')
            ->assertOk()
            ->assertSee('Alterar senha')
            ->assertSee($this->atleta->email);
    }

    public function test_troca_a_senha_com_a_senha_atual_correta(): void
    {
        $this->trocar([
            'senha_atual' => 'senha-atual-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect('/alterar-senha')->assertSessionHas('success');

        $this->atleta->refresh();

        $this->assertTrue(Hash::check('senha-nova-456', $this->atleta->password));
        $this->assertFalse(Hash::check('senha-atual-123', $this->atleta->password));
    }

    public function test_senha_atual_errada_e_recusada(): void
    {
        // A trava que importa: sem ela, quem pega uma sessão aberta troca a
        // senha e o dono perde a conta.
        $this->trocar([
            'senha_atual' => 'chute',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertSessionHasErrors('senha_atual');

        $this->assertTrue(Hash::check('senha-atual-123', $this->atleta->fresh()->password));
    }

    public function test_confirmacao_diferente_e_recusada(): void
    {
        $this->trocar([
            'senha_atual' => 'senha-atual-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'digitei-outra-coisa',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('senha-atual-123', $this->atleta->fresh()->password));
    }

    public function test_senha_curta_e_recusada(): void
    {
        $this->trocar([
            'senha_atual' => 'senha-atual-123',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors('password');
    }

    public function test_repetir_a_senha_atual_e_recusado(): void
    {
        $this->trocar([
            'senha_atual' => 'senha-atual-123',
            'password' => 'senha-atual-123',
            'password_confirmation' => 'senha-atual-123',
        ])->assertSessionHasErrors('password');
    }

    public function test_um_usuario_nao_troca_a_senha_de_outro(): void
    {
        // A senha trocada é sempre a de quem está logado: o formulário não tem
        // (nem pode ter) campo de usuário.
        $vizinho = User::factory()->create(['password' => Hash::make('senha-do-vizinho')]);

        $this->trocar([
            'senha_atual' => 'senha-atual-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertRedirect('/alterar-senha');

        $this->assertTrue(Hash::check('senha-do-vizinho', $vizinho->fresh()->password));
    }

    public function test_o_menu_aponta_para_a_tela_e_nao_para_lugar_nenhum(): void
    {
        $this->actingAs($this->atleta)
            ->get('/my-subscriptions')
            ->assertOk()
            ->assertSee('href="' . route('senha.editar') . '"', false)
            ->assertDontSee('<a class="dropdown-item" href="#!">', false);
    }
}
