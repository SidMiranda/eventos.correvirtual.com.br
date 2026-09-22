<?php

namespace Tests\Feature\Admin;

use App\Models\Organizer;
use App\Models\User;
use App\Support\ImagensDoSobre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A tela do bloco "Sobre nós" no painel.
 *
 * Registro único por organizador — não há listagem nem id na URL, então o
 * isolamento aqui é outro: o controller nunca aceita um organizador vindo da
 * requisição, sempre o do usuário logado.
 */
class SobreNosTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('r2');

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizadorA = Organizer::factory()->create(['name' => 'Organizador A']);
        $this->organizadorB = Organizer::factory()->create([
            'name' => 'Organizador B',
            'about_title' => 'O sobre do B',
            'about_text' => 'Texto do B.',
        ]);

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);
    }

    private function dadosValidos(array $sobrescreve = []): array
    {
        return array_merge([
            'about_badge' => 'SOBRE A PLATAFORMA',
            'about_title' => 'Corre Virtual - Desafie seus limites',
            'about_text' => "Primeiro parágrafo.\n\nSegundo parágrafo.",
            'about_button_label' => 'COMEÇAR MEU DESAFIO',
            'about_button_url' => 'https://correvirtual.com.br/desafios',
        ], $sobrescreve);
    }

    public function test_a_tela_abre_com_o_conteudo_atual(): void
    {
        $this->organizadorA->update(['about_title' => 'Título que já existe']);

        $this->actingAs($this->adminA)
            ->get('/admin/sobre')
            ->assertOk()
            ->assertSee('Título que já existe')
            ->assertSee('name="about_text"', false);
    }

    public function test_salvar_grava_no_organizador_do_usuario_logado(): void
    {
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos())
            ->assertRedirect('/admin/sobre');

        $this->organizadorA->refresh();

        $this->assertSame('Corre Virtual - Desafie seus limites', $this->organizadorA->about_title);
        $this->assertSame("Primeiro parágrafo.\n\nSegundo parágrafo.", $this->organizadorA->about_text);
        $this->assertSame('https://correvirtual.com.br/desafios', $this->organizadorA->about_button_url);
    }

    public function test_o_organizador_do_formulario_e_ignorado(): void
    {
        // Não existe id na URL, mas mandar um na marra também não pode pegar.
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos([
                'organizer_id' => $this->organizadorB->id,
                'about_title' => 'Invadido',
            ]));

        $this->assertSame('O sobre do B', $this->organizadorB->fresh()->about_title);
        $this->assertSame('Invadido', $this->organizadorA->fresh()->about_title);
    }

    public function test_link_do_botao_sem_esquema_e_recusado(): void
    {
        // Sem `https://` o navegador lê como caminho relativo e o botão leva
        // para dentro do próprio site.
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos(['about_button_url' => 'correvirtual.com.br']))
            ->assertSessionHasErrors('about_button_url');
    }

    public function test_tudo_e_opcional(): void
    {
        // Organizador que ainda não escreveu nada precisa conseguir salvar a
        // tela sem preencher campo nenhum — o bloco simplesmente não aparece.
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', [])
            ->assertRedirect('/admin/sobre')
            ->assertSessionHasNoErrors();
    }

    public function test_a_foto_vai_para_o_caminho_do_organizador(): void
    {
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos() + [
                'foto' => UploadedFile::fake()->image('equipe.jpg', 900, 900),
            ])
            ->assertRedirect('/admin/sobre');

        Storage::disk('r2')->assertExists(ImagensDoSobre::caminho($this->organizadorA));
    }

    public function test_trocar_so_a_foto_move_a_versao_da_url(): void
    {
        // O caminho no bucket é fixo e a gravação é `immutable`: sem mexer no
        // `updated_at`, o CDN continuaria servindo a foto antiga por um ano.
        $this->organizadorA->update($this->dadosValidos());
        $antes = $this->organizadorA->fresh()->updated_at;

        $this->travel(2)->seconds();

        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos() + [
                'foto' => UploadedFile::fake()->image('nova.jpg', 900, 900),
            ]);

        $this->assertTrue($this->organizadorA->fresh()->updated_at->gt($antes));
    }

    public function test_remover_a_foto_apaga_do_bucket(): void
    {
        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos() + [
                'foto' => UploadedFile::fake()->image('equipe.jpg', 900, 900),
            ]);

        Storage::disk('r2')->assertExists(ImagensDoSobre::caminho($this->organizadorA));

        $this->actingAs($this->adminA)
            ->put('/admin/sobre', $this->dadosValidos() + ['apagar_foto' => 1]);

        Storage::disk('r2')->assertMissing(ImagensDoSobre::caminho($this->organizadorA));
    }

    public function test_deslogado_e_atleta_nao_entram(): void
    {
        $this->get('/admin/sobre')->assertRedirect('/login');
        $this->put('/admin/sobre', $this->dadosValidos())->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/sobre')->assertForbidden();
        $this->actingAs($atleta)->put('/admin/sobre', $this->dadosValidos())->assertForbidden();
    }
}
