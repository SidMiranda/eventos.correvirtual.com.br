<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Minha conta" › Perfil (docs/specs/area-do-atleta.md, fatia 2).
 *
 * O atleta muda nome, celular, cidade e PCD. Data de nascimento, sexo, CPF,
 * CPF do responsável e e-mail ficam travados (decisão do dono, 2026-09-25).
 */
class PerfilDoAtletaTest extends TestCase
{
    use RefreshDatabase;

    private User $atleta;
    private City $mogi;
    private City $campinas;

    protected function setUp(): void
    {
        parent::setUp();

        Organizer::factory()->create(['domain' => 'localhost']);
        $this->mogi = City::factory()->chamada('Mogi Guaçu')->create();
        $this->campinas = City::factory()->chamada('Campinas')->create();

        $this->atleta = User::factory()->create([
            'role' => 'athlete',
            'name' => 'Fulano de Tal',
            'email' => 'fulano@teste.com',
            'cpf' => '39053344705',
            'guardian_cpf' => '52998224725',
            'birth_date' => '2010-05-10',
            'sex' => 'male',
            'phone' => '19999999999',
            'city_id' => $this->mogi->id,
            'is_pcd' => false,
        ]);
    }

    private function salvar(array $dados)
    {
        return $this->actingAs($this->atleta)->put('/minha-conta/perfil', array_merge([
            'name' => 'Fulano de Tal',
            'phone' => '(19) 99999-9999',
            'cidade' => $this->mogi->nomeCompleto(),
            'city_id' => $this->mogi->id,
        ], $dados));
    }

    public function test_deslogado_vai_para_o_login(): void
    {
        $this->get('/minha-conta/perfil')->assertRedirect('/login');
        $this->put('/minha-conta/perfil', [])->assertRedirect('/login');
    }

    public function test_a_tela_mostra_os_dados_e_os_travados_sem_campo(): void
    {
        $this->actingAs($this->atleta)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee('value="Fulano de Tal"', false)
            ->assertSee('(19) 99999-9999')
            ->assertSee('Mogi Guaçu')
            ->assertSee('fulano@teste.com')
            ->assertSee('390.533.447-05')
            ->assertSee('10/05/2010')
            ->assertSee('Masculino')
            ->assertSee('529.982.247-25')
            // Travados não têm campo.
            ->assertDontSee('name="email"', false)
            ->assertDontSee('name="cpf"', false)
            ->assertDontSee('name="birth_date"', false)
            ->assertDontSee('name="sex"', false)
            ->assertDontSee('name="guardian_cpf"', false)
            ->assertSee(route('senha.editar'), false);
    }

    public function test_altera_nome_celular_cidade_e_pcd(): void
    {
        $this->salvar([
            'name' => '  Fulano da Silva ',
            'phone' => '(11) 98888-7777',
            'cidade' => $this->campinas->nomeCompleto(),
            'city_id' => $this->campinas->id,
            'is_pcd' => '1',
        ])->assertRedirect('/minha-conta/perfil')->assertSessionHasNoErrors();

        $atleta = $this->atleta->fresh();
        $this->assertSame('Fulano da Silva', $atleta->name);
        $this->assertSame('11988887777', $atleta->phone);
        $this->assertSame($this->campinas->id, $atleta->city_id);
        $this->assertTrue($atleta->is_pcd);
    }

    public function test_desmarcar_pcd_grava_nao(): void
    {
        $this->atleta->update(['is_pcd' => true]);

        $this->salvar([])->assertSessionHasNoErrors();

        $this->assertFalse($this->atleta->fresh()->is_pcd);
    }

    public function test_campos_travados_nao_mudam_mesmo_enviados(): void
    {
        $this->salvar([
            'email' => 'outro@teste.com',
            'cpf' => '52998224725',
            'guardian_cpf' => '39053344705',
            'birth_date' => '1950-01-01',
            'sex' => 'female',
            'role' => 'super_admin',
            'organizer_id' => 99,
        ])->assertSessionHasNoErrors();

        $atleta = $this->atleta->fresh();
        $this->assertSame('fulano@teste.com', $atleta->email);
        $this->assertSame('39053344705', $atleta->cpf);
        $this->assertSame('52998224725', $atleta->guardian_cpf);
        $this->assertSame('2010-05-10', \Carbon\Carbon::parse($atleta->birth_date)->format('Y-m-d'));
        $this->assertSame('male', $atleta->sex);
        $this->assertSame('athlete', $atleta->role);
        $this->assertNull($atleta->organizer_id);
    }

    public function test_cidade_digitada_sem_escolher_da_lista_e_recusada(): void
    {
        $this->salvar(['cidade' => 'Cidade Inventada', 'city_id' => ''])
            ->assertSessionHasErrors('city_id');

        $this->assertSame($this->mogi->id, $this->atleta->fresh()->city_id);
    }

    public function test_nome_e_celular_obrigatorios(): void
    {
        $this->salvar(['name' => '   ', 'phone' => '123'])
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertSame('Fulano de Tal', $this->atleta->fresh()->name);
    }

    public function test_as_abas_levam_as_duas_telas(): void
    {
        $this->actingAs($this->atleta)->get('/minha-conta')
            ->assertOk()
            ->assertSee(route('conta.perfil'), false);

        $this->actingAs($this->atleta)->get('/minha-conta/perfil')
            ->assertOk()
            ->assertSee(route('conta.inscricoes'), false)
            ->assertSeeInOrder(['Inscrições</a>', 'aria-current="page"', 'Perfil</a>'], false);
    }
}
