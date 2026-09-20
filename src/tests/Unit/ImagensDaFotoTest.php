<?php

namespace Tests\Unit;

use App\Support\ImagensDaFoto;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * O recorte quadrado da foto da galeria.
 *
 * Toda foto vira 900×900 JPEG, recortada pelo centro — é o que a grade de
 * seis colunas pede e o que impede 12 originais de celular numa página.
 */
class ImagensDaFotoTest extends TestCase
{
    private function imagem(int $largura, int $altura): string
    {
        $gd = imagecreatetruecolor($largura, $altura);
        imagefilledrectangle($gd, 0, 0, $largura, $altura, imagecolorallocate($gd, 30, 120, 200));

        ob_start();
        imagejpeg($gd, null, 90);

        return ob_get_clean();
    }

    private function dimensoes(string $bytes): array
    {
        [$largura, $altura, $tipo] = getimagesizefromstring($bytes);

        return [$largura, $altura, $tipo];
    }

    public function test_deitada_vira_quadrado_de_700(): void
    {
        [$largura, $altura, $tipo] = $this->dimensoes(ImagensDaFoto::quadrada($this->imagem(1200, 800)));

        $this->assertSame(700, $largura);
        $this->assertSame(700, $altura);
        $this->assertSame(IMAGETYPE_JPEG, $tipo);
    }

    public function test_em_pe_tambem_vira_quadrado_de_700(): void
    {
        [$largura, $altura] = $this->dimensoes(ImagensDaFoto::quadrada($this->imagem(500, 900)));

        $this->assertSame(700, $largura);
        $this->assertSame(700, $altura);
    }

    public function test_pequena_e_ampliada_ate_o_quadrado(): void
    {
        // Menor que 700 de um lado: recorta o quadrado do lado menor e escala.
        // Melhor uma foto um pouco macia que um buraco de tamanho diferente na
        // grade.
        [$largura, $altura] = $this->dimensoes(ImagensDaFoto::quadrada($this->imagem(400, 300)));

        $this->assertSame(700, $largura);
        $this->assertSame(700, $altura);
    }

    public function test_a_inteira_cabe_em_1000_mantendo_a_proporcao(): void
    {
        [$largura, $altura, $tipo] = $this->dimensoes(ImagensDaFoto::inteira($this->imagem(1200, 800)));

        $this->assertSame(1000, $largura);
        $this->assertSame(667, $altura);
        $this->assertSame(IMAGETYPE_JPEG, $tipo);

        // Em pé e grande: o lado maior vai a 1000 e a largura acompanha.
        [$largura, $altura] = $this->dimensoes(ImagensDaFoto::inteira($this->imagem(1500, 2700)));

        $this->assertSame(556, $largura);
        $this->assertSame(1000, $altura);
    }

    public function test_a_inteira_nao_amplia_foto_pequena(): void
    {
        // Ampliar só borraria; a foto inteira fica do tamanho que veio.
        [$largura, $altura] = $this->dimensoes(ImagensDaFoto::inteira($this->imagem(400, 300)));

        $this->assertSame(400, $largura);
        $this->assertSame(300, $altura);
    }

    public function test_bytes_que_nao_sao_imagem_lancam_excecao_com_mensagem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não é uma imagem');

        ImagensDaFoto::quadrada('isto não é uma imagem');
    }
}
