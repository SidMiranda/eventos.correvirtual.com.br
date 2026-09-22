<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Organizer;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CPF de verdade no cadastro, e o CPF do responsável para menor de idade.
 *
 * Até 2026-09-22 a regra era `size:11` e nada mais — "11111111111" entrava.
 * O CPF identifica o atleta na largada e no comprovante de pagamento: número
 * inventado só aparece como problema no dia da prova.
 *
 * O campo do responsável some da tela por JavaScript quando a pessoa é maior
 * de idade, mas quem decide é o servidor: esconder no front não é validar.
 */
class CpfDoResponsavelTest extends TestCase
{
    use RefreshDatabase;

    /** CPFs com dígitos verificadores corretos, para os testes. */
    private const CPF_ATLETA = '390.533.447-05';
    private const CPF_RESPONSAVEL = '529.982.247-25';

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
            'birth_date' => now()->subYears(30)->format('Y-m-d'),
            'sex' => 'male',
            'phone' => '(19) 99999-9999',
            'email' => 'fulano@teste.com',
            'cpf' => self::CPF_ATLETA,
            'password' => 'segredo123',
            'cidade' => $cidade->nomeCompleto(),
            'city_id' => $cidade->id,
        ], $sobrescreve);
    }

    /** Nascimento de quem tem 17 anos e 11 meses — menor até amanhã. */
    private function nascimentoDeMenor(): string
    {
        return now()->subYears(18)->addMonth()->format('Y-m-d');
    }

    /*
    |--------------------------------------------------------------------------
    | A conta do CPF
    |--------------------------------------------------------------------------
    */

    public function test_a_regra_confere_os_digitos_verificadores(): void
    {
        $this->assertTrue(Cpf::valido('390.533.447-05'));
        $this->assertTrue(Cpf::valido('39053344705'));
        $this->assertTrue(Cpf::valido('529.982.247-25'));

        // Dígito verificador errado.
        $this->assertFalse(Cpf::valido('390.533.447-06'));
        $this->assertFalse(Cpf::valido('12345678900'));
    }

    public function test_numero_repetido_nao_e_cpf(): void
    {
        // Passam na conta dos dígitos, mas é o que se digita para escapar do
        // campo — e era exatamente o que entrava antes desta regra.
        foreach (['00000000000', '11111111111', '99999999999'] as $cpf) {
            $this->assertFalse(Cpf::valido($cpf), $cpf);
        }
    }

    public function test_tamanho_errado_nao_e_cpf(): void
    {
        $this->assertFalse(Cpf::valido('123'));
        $this->assertFalse(Cpf::valido('3905334470512'));
        $this->assertFalse(Cpf::valido(''));
    }

    public function test_o_cadastro_recusa_cpf_invalido(): void
    {
        $this->post('/register', $this->dadosValidos(['cpf' => '111.111.111-11']))
            ->assertSessionHasErrors('cpf');

        $this->assertDatabaseMissing('users', ['email' => 'fulano@teste.com']);
    }

    /*
    |--------------------------------------------------------------------------
    | O responsável
    |--------------------------------------------------------------------------
    */

    public function test_maior_de_idade_nao_precisa_de_responsavel(): void
    {
        $this->post('/register', $this->dadosValidos())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'fulano@teste.com')->first()->guardian_cpf);
    }

    public function test_menor_de_idade_sem_responsavel_nao_passa(): void
    {
        $this->post('/register', $this->dadosValidos(['birth_date' => $this->nascimentoDeMenor()]))
            ->assertSessionHasErrors('guardian_cpf');

        $this->assertDatabaseMissing('users', ['email' => 'fulano@teste.com']);
    }

    public function test_menor_de_idade_com_responsavel_valido_passa(): void
    {
        $this->post('/register', $this->dadosValidos([
            'birth_date' => $this->nascimentoDeMenor(),
            'guardian_cpf' => self::CPF_RESPONSAVEL,
        ]))->assertRedirect()->assertSessionHasNoErrors();

        // Guardado só com os dígitos, como o CPF do atleta.
        $this->assertSame('52998224725', User::where('email', 'fulano@teste.com')->first()->guardian_cpf);
    }

    public function test_o_cpf_do_responsavel_tambem_passa_pela_conta(): void
    {
        $this->post('/register', $this->dadosValidos([
            'birth_date' => $this->nascimentoDeMenor(),
            'guardian_cpf' => '111.111.111-11',
        ]))->assertSessionHasErrors('guardian_cpf');
    }

    public function test_o_responsavel_nao_pode_ser_o_proprio_atleta(): void
    {
        $this->post('/register', $this->dadosValidos([
            'birth_date' => $this->nascimentoDeMenor(),
            'guardian_cpf' => self::CPF_ATLETA,
        ]))->assertSessionHasErrors('guardian_cpf');
    }

    public function test_quem_faz_18_hoje_nao_precisa_de_responsavel(): void
    {
        // A fronteira: 18 anos exatos já responde por si.
        $this->post('/register', $this->dadosValidos([
            'birth_date' => now()->subYears(18)->format('Y-m-d'),
        ]))->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_maior_de_idade_que_mandar_responsavel_tem_o_cpf_conferido(): void
    {
        // O campo fica escondido, mas quem manda na marra não escapa da conta.
        $this->post('/register', $this->dadosValidos(['guardian_cpf' => '123']))
            ->assertSessionHasErrors('guardian_cpf');
    }

    public function test_o_formulario_traz_o_campo_escondido(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="guardian_cpf"', false)
            ->assertSee('id="blocoResponsavel" hidden', false);
    }
}
