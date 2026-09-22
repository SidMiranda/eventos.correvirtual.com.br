<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A cidade do atleta: busca e vínculo no cadastro.
 *
 * O campo é obrigatório e a cidade tem de ser **escolhida da lista** (decisão
 * do dono em 2026-09-22): o que vale é o `city_id`, não o texto digitado.
 * Digitar o nome e não clicar na sugestão não conta — senão o dado chegaria
 * como texto solto e "Mogi Guaçu" e "mogi guacu" virariam duas cidades.
 */
class CidadeNoCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Organizer::factory()->create(['domain' => 'localhost']);
    }

    private function dadosValidos(array $sobrescreve = []): array
    {
        $cidade = City::factory()->chamada('Mogi Guaçu')->create();

        return array_merge([
            'name' => 'Fulano de Tal',
            'birth_date' => '1990-05-20',
            'sex' => 'male',
            'phone' => '(19) 99999-9999',
            'email' => 'fulano@teste.com',
            'cpf' => '390.533.447-05',
            'password' => 'segredo123',
            'cidade' => $cidade->nomeCompleto(),
            'city_id' => $cidade->id,
        ], $sobrescreve);
    }

    /*
    |--------------------------------------------------------------------------
    | A busca
    |--------------------------------------------------------------------------
    */

    public function test_a_busca_exige_tres_letras(): void
    {
        City::factory()->chamada('Mogi Guaçu')->create();

        // Com menos que isso a lista viria enorme e sem ajudar a escolher.
        $this->getJson('/cidades?q=mo')->assertOk()->assertJsonCount(0);
        $this->getJson('/cidades?q=')->assertOk()->assertJsonCount(0);
        $this->getJson('/cidades?q=mog')->assertOk()->assertJsonCount(1);
    }

    public function test_a_busca_ignora_acento_e_caixa(): void
    {
        // Ninguém digita "Mogi Guaçu" com ç no celular.
        City::factory()->chamada('Mogi Guaçu')->create();

        foreach (['guacu', 'GUAÇU', 'Guacu', 'mogi gua'] as $termo) {
            $this->getJson('/cidades?q=' . urlencode($termo))
                ->assertOk()
                ->assertJsonFragment(['nome' => 'Mogi Guaçu - SP']);
        }
    }

    public function test_quem_comeca_pelo_termo_vem_primeiro(): void
    {
        // Quem digita "mogi" quer "Mogi Guaçu", não "Itamogi".
        City::factory()->chamada('Itamogi', 'MG')->create();
        City::factory()->chamada('Mogi Guaçu')->create();

        $resposta = $this->getJson('/cidades?q=mogi')->assertOk();

        $this->assertSame('Mogi Guaçu - SP', $resposta->json('0.nome'));
    }

    public function test_a_busca_devolve_o_id_e_o_nome_com_a_uf(): void
    {
        $cidade = City::factory()->chamada('Mogi Mirim')->create();

        $this->getJson('/cidades?q=mogi mi')
            ->assertOk()
            ->assertExactJson([['id' => $cidade->id, 'nome' => 'Mogi Mirim - SP']]);
    }

    public function test_a_busca_nao_devolve_a_lista_inteira(): void
    {
        City::factory()->count(30)->chamada('Santa Rosa')->create();

        $this->getJson('/cidades?q=santa')
            ->assertOk()
            ->assertJsonCount(City::LIMITE_DA_BUSCA);
    }

    public function test_a_busca_e_publica(): void
    {
        // O formulário de cadastro é de quem ainda não tem conta.
        City::factory()->chamada('Campinas')->create();

        $this->getJson('/cidades?q=campi')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | O cadastro
    |--------------------------------------------------------------------------
    */

    public function test_a_cidade_escolhida_fica_vinculada_ao_atleta(): void
    {
        $dados = $this->dadosValidos();

        $this->post('/register', $dados)->assertRedirect();

        $atleta = User::where('email', 'fulano@teste.com')->first();

        $this->assertSame($dados['city_id'], $atleta->city_id);
        $this->assertSame('Mogi Guaçu - SP', $atleta->city->nomeCompleto());
    }

    public function test_cadastro_sem_cidade_nao_passa(): void
    {
        $this->post('/register', $this->dadosValidos(['cidade' => null, 'city_id' => null]))
            ->assertSessionHasErrors('cidade');

        $this->assertDatabaseMissing('users', ['email' => 'fulano@teste.com']);
    }

    public function test_digitou_e_nao_escolheu_da_lista_nao_passa(): void
    {
        // O que vale é o id. Digitar o nome e não clicar na sugestão deixaria
        // o dado entrar como texto solto.
        $this->post('/register', $this->dadosValidos(['cidade' => 'Mogi', 'city_id' => null]))
            ->assertSessionHasErrors('city_id');

        $this->assertDatabaseMissing('users', ['email' => 'fulano@teste.com']);
    }

    public function test_id_de_cidade_inexistente_e_recusado(): void
    {
        $this->post('/register', $this->dadosValidos([
            'cidade' => 'Cidade Inventada - XX',
            'city_id' => 999999,
        ]))->assertSessionHasErrors('city_id');
    }

    public function test_o_formulario_tem_o_campo(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="cidade"', false)
            ->assertSee('name="city_id"', false);
    }
}
