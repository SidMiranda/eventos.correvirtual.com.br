<?php

namespace Tests\Unit;

use App\Support\TextoDoSite;
use PHPUnit\Framework\TestCase;

/**
 * A regra única do texto livre digitado no painel.
 *
 * Escape primeiro, `**negrito**` depois, quebra de linha por último — nessa
 * ordem, porque é ela que garante que as únicas tags no resultado saíram daqui
 * e não do formulário.
 */
class TextoDoSiteTest extends TestCase
{
    public function test_a_quebra_de_linha_vira_br(): void
    {
        $this->assertSame(
            "Primeira<br />\nSegunda",
            TextoDoSite::paraHtml("Primeira\nSegunda")
        );
    }

    public function test_a_linha_em_branco_sobrevive(): void
    {
        $this->assertSame(
            "Um<br />\n<br />\nDois",
            TextoDoSite::paraHtml("Um\n\nDois")
        );
    }

    public function test_html_digitado_e_escapado(): void
    {
        $saida = TextoDoSite::paraHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $saida);
        $this->assertStringContainsString('&lt;script&gt;', $saida);
    }

    public function test_negrito_com_dois_asteriscos(): void
    {
        $this->assertSame(
            'A <strong>Corre Virtual</strong> é assim',
            TextoDoSite::paraHtml('A **Corre Virtual** é assim')
        );
    }

    public function test_dois_negritos_na_mesma_linha_nao_viram_um_so(): void
    {
        // Com um quantificador guloso, "**a** e **b**" viraria um único
        // <strong> engolindo o " e " do meio.
        $this->assertSame(
            '<strong>a</strong> e <strong>b</strong>',
            TextoDoSite::paraHtml('**a** e **b**')
        );
    }

    public function test_asteriscos_soltos_nao_abrem_negrito(): void
    {
        // Multiplicação e ênfase acidental não podem virar tag.
        $this->assertSame('2 ** 3 = 8', TextoDoSite::paraHtml('2 ** 3 = 8'));
    }

    public function test_negrito_nao_serve_para_injetar_html(): void
    {
        // O escape acontece ANTES: o que está entre os asteriscos já chegou
        // aqui como texto, então vira conteúdo do <strong>, não marcação.
        $saida = TextoDoSite::paraHtml('**<img src=x onerror=alert(1)>**');

        $this->assertStringNotContainsString('<img', $saida);
        $this->assertStringContainsString('<strong>&lt;img', $saida);
    }

    public function test_vazio_e_nulo_devolvem_string_vazia(): void
    {
        $this->assertSame('', TextoDoSite::paraHtml(null));
        $this->assertSame('', TextoDoSite::paraHtml(''));
        $this->assertSame('', TextoDoSite::paraHtml('   '));
    }
}
