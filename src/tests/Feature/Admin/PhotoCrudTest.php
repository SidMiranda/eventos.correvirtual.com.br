<?php

namespace Tests\Feature\Admin;

use App\Models\Organizer;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A galeria de fotos no painel.
 *
 * Mesmas garantias dos outros cadastros do organizador — nasce no organizador
 * de quem está logado, e um não enxerga nem mexe na foto do outro — mais o
 * que é só daqui: o envio em lote e a regra "linha existe => imagem existe".
 */
class PhotoCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizadorA;
    private Organizer $organizadorB;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        // Disco em memória: os testes nunca tocam o bucket real.
        Storage::fake('r2');

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->organizadorA = Organizer::factory()->create();
        $this->organizadorB = Organizer::factory()->create();

        $this->adminA = User::factory()->create([
            'role' => 'organizer_admin',
            'organizer_id' => $this->organizadorA->id,
        ]);
    }

    private function imagem(string $nome = 'foto.jpg', int $largura = 1200, int $altura = 800): UploadedFile
    {
        return UploadedFile::fake()->image($nome, $largura, $altura);
    }

    private function fotoDoA(array $extra = []): Photo
    {
        return Photo::factory()->create(array_merge(['organizer_id' => $this->organizadorA->id], $extra));
    }

    /*
    |--------------------------------------------------------------------------
    | Acesso
    |--------------------------------------------------------------------------
    */

    public function test_deslogado_vai_para_o_login_e_atleta_leva_403(): void
    {
        $this->get('/admin/fotos')->assertRedirect('/login');

        $atleta = User::factory()->create(['role' => 'athlete']);
        $this->actingAs($atleta)->get('/admin/fotos')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Envio em lote
    |--------------------------------------------------------------------------
    */

    public function test_envio_de_tres_arquivos_cria_tres_fotos_no_caminho_derivado(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/fotos', [
                'fotos' => [
                    $this->imagem('largada.jpg'),
                    $this->imagem('medalha.png', 800, 800),
                    $this->imagem('pastel.jpg', 600, 900),
                ],
            ])
            ->assertRedirect('/admin/fotos')
            ->assertSessionHas('sucesso', '3 fotos adicionadas.');

        $fotos = Photo::orderBy('id')->get();

        $this->assertCount(3, $fotos);
        $this->assertSame([1, 2, 3], $fotos->pluck('position')->all());

        foreach ($fotos as $foto) {
            $this->assertSame($this->organizadorA->id, $foto->organizer_id);
            $this->assertTrue($foto->active);

            // O caminho sai do organizador e do id — nunca do nome enviado.
            // Duas derivadas: o quadrado da grade e a foto inteira do hover.
            Storage::disk('r2')->assertExists("publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}.jpg");
            Storage::disk('r2')->assertExists("publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}-inteira.jpg");
        }

        Storage::disk('r2')->assertMissing('publico/largada.jpg');
    }

    public function test_as_novas_entram_no_fim_da_ordem(): void
    {
        $this->fotoDoA(['position' => 7]);

        $this->actingAs($this->adminA)->post('/admin/fotos', ['fotos' => [$this->imagem()]]);

        $this->assertSame(8, Photo::orderByDesc('id')->first()->position);
    }

    public function test_a_imagem_gravada_e_um_quadrado(): void
    {
        $this->actingAs($this->adminA)->post('/admin/fotos', ['fotos' => [$this->imagem('deitada.jpg', 1200, 800)]]);

        $foto = Photo::firstOrFail();
        $conteudo = Storage::disk('r2')->get("publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}.jpg");
        [$largura, $altura, $tipo] = getimagesizefromstring($conteudo);

        $this->assertSame(700, $largura);
        $this->assertSame(700, $altura);
        $this->assertSame(IMAGETYPE_JPEG, $tipo);

        // E a inteira guarda a proporção original.
        $inteira = Storage::disk('r2')->get("publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}-inteira.jpg");
        [$largura, $altura] = getimagesizefromstring($inteira);

        $this->assertSame(1000, $largura);
        $this->assertSame(667, $altura);
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado_sem_criar_linha(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/fotos', [
                'fotos' => [UploadedFile::fake()->create('regulamento.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('fotos.0');

        $this->assertDatabaseCount('photos', 0);
        $this->assertSame([], Storage::disk('r2')->allFiles());
    }

    public function test_envio_sem_arquivo_e_recusado(): void
    {
        $this->actingAs($this->adminA)
            ->post('/admin/fotos', [])
            ->assertSessionHasErrors('fotos');

        $this->assertDatabaseCount('photos', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Isolamento entre organizadores
    |--------------------------------------------------------------------------
    */

    public function test_listagem_mostra_so_as_fotos_do_proprio_organizador(): void
    {
        $minha = $this->fotoDoA(['caption' => 'Largada Da Minha Prova']);
        $alheia = Photo::factory()->create(['organizer_id' => $this->organizadorB->id, 'caption' => 'Foto Do Vizinho']);

        $this->actingAs($this->adminA)
            ->get('/admin/fotos')
            ->assertOk()
            ->assertSee('Largada Da Minha Prova')
            ->assertSee("fotos/{$minha->id}.jpg", false)
            ->assertDontSee('Foto Do Vizinho')
            ->assertDontSee("fotos/{$alheia->id}.jpg", false);
    }

    public function test_nao_edita_nem_apaga_foto_de_outro_organizador(): void
    {
        $alheia = Photo::factory()->create(['organizer_id' => $this->organizadorB->id, 'caption' => 'Intacta']);

        $this->actingAs($this->adminA)->get("/admin/fotos/{$alheia->id}/edit")->assertNotFound();

        $this->actingAs($this->adminA)
            ->put("/admin/fotos/{$alheia->id}", ['caption' => 'Invadida', 'active' => 1])
            ->assertNotFound();

        $this->actingAs($this->adminA)->delete("/admin/fotos/{$alheia->id}")->assertNotFound();

        $this->assertSame('Intacta', $alheia->fresh()->caption);
    }

    /*
    |--------------------------------------------------------------------------
    | Edição e exclusão
    |--------------------------------------------------------------------------
    */

    public function test_edita_legenda_link_ordem_e_ativo(): void
    {
        $foto = $this->fotoDoA(['position' => 1, 'active' => true]);

        $this->actingAs($this->adminA)
            ->put("/admin/fotos/{$foto->id}", [
                'caption' => 'Chegada',
                'link_url' => 'https://www.instagram.com/p/xyz/',
                'position' => 4,
                'active' => 0,
            ])
            ->assertRedirect('/admin/fotos');

        $foto->refresh();
        $this->assertSame('Chegada', $foto->caption);
        $this->assertSame('https://www.instagram.com/p/xyz/', $foto->link_url);
        $this->assertSame(4, $foto->position);
        $this->assertFalse($foto->active);
    }

    public function test_link_sem_esquema_e_recusado(): void
    {
        $foto = $this->fotoDoA();

        $this->actingAs($this->adminA)
            ->put("/admin/fotos/{$foto->id}", ['link_url' => 'instagram.com/p/xyz', 'active' => 1])
            ->assertSessionHasErrors('link_url');

        $this->assertNull($foto->fresh()->link_url);
    }

    public function test_trocar_a_imagem_regrava_o_mesmo_caminho(): void
    {
        $foto = $this->fotoDoA();
        $caminho = "publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}.jpg";
        Storage::disk('r2')->put($caminho, 'antiga');

        $this->actingAs($this->adminA)
            ->put("/admin/fotos/{$foto->id}", ['foto' => $this->imagem('nova.jpg', 1000, 1000), 'active' => 1])
            ->assertRedirect('/admin/fotos');

        $this->assertNotSame('antiga', Storage::disk('r2')->get($caminho));
        // O quadrado e a inteira, nada mais: trocar não deixa arquivo velho.
        $this->assertCount(2, Storage::disk('r2')->allFiles());
    }

    public function test_apagar_remove_a_linha_e_os_arquivos(): void
    {
        $foto = $this->fotoDoA();
        $caminho = "publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}.jpg";
        $caminhoInteira = "publico/organizadores/{$this->organizadorA->id}/fotos/{$foto->id}-inteira.jpg";
        Storage::disk('r2')->put($caminho, 'conteudo');
        Storage::disk('r2')->put($caminhoInteira, 'conteudo');

        $this->actingAs($this->adminA)
            ->delete("/admin/fotos/{$foto->id}")
            ->assertRedirect('/admin/fotos');

        $this->assertDatabaseMissing('photos', ['id' => $foto->id]);
        Storage::disk('r2')->assertMissing($caminho);
        Storage::disk('r2')->assertMissing($caminhoInteira);
    }
}
