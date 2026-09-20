<?php

namespace App\Support;

use App\Models\Photo;
use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * As imagens de uma foto da galeria, no bucket público do R2.
 *
 * Duas derivadas por foto, as duas JPEG:
 *
 * - o QUADRADO da grade (`{id}.jpg`): recorte central, 700×700. Na tela ele
 *   mede uns 350 px; o dobro no arquivo é para não ficar borrado em tela
 *   retina. As fotos chegam de tamanhos e proporções variadas — o recorte
 *   pelo centro, preenchendo, é o que deixa a grade regular;
 * - a foto INTEIRA (`{id}-inteira.jpg`): tudo o que a foto mostra, cabendo
 *   num quadro de 1000 px sem ampliar. É o que aparece no hover, em fade,
 *   por cima do recorte — e só é baixada quando alguém passa o mouse.
 *
 * O original não fica: o painel recebe foto de celular de 3–5 MB e 4000 px,
 * e não há uso para ela aqui. Ver docs/specs/galeria-de-fotos.md.
 */
class ImagensDaFoto
{
    /** O quadrado da grade: ~350 px na tela, o dobro no arquivo (retina). */
    public const LADO = 700;

    /** A foto inteira cabe num quadro deste lado; menor que isso, fica como está. */
    public const LADO_INTEIRA = 1000;

    public const QUALIDADE = 82;

    public static function salvar(Photo $photo, UploadedFile $arquivo): void
    {
        $imagem = self::abrir(file_get_contents($arquivo->getRealPath()));

        ImagemPublica::salvarConteudo(self::caminho($photo), self::quadradaDe($imagem), 'image/jpeg');
        ImagemPublica::salvarConteudo(self::caminhoInteira($photo), self::inteiraDe($imagem), 'image/jpeg');

        imagedestroy($imagem);
    }

    public static function apagar(Photo $photo): void
    {
        ImagemPublica::apagar(self::caminho($photo));
        ImagemPublica::apagar(self::caminhoInteira($photo));
    }

    /**
     * Derivado do organizador e do id, nunca do nome do arquivo enviado —
     * caminho montado com dado do navegador deixaria um organizador escrever
     * na pasta de outro.
     */
    public static function caminho(Photo $photo): string
    {
        return "publico/organizadores/{$photo->organizer_id}/fotos/{$photo->id}.jpg";
    }

    public static function caminhoInteira(Photo $photo): string
    {
        return "publico/organizadores/{$photo->organizer_id}/fotos/{$photo->id}-inteira.jpg";
    }

    /*
    |--------------------------------------------------------------------------
    | As derivadas, a partir dos bytes
    |--------------------------------------------------------------------------
    */

    /** @throws InvalidArgumentException quando os bytes não são uma imagem que o GD abra */
    public static function quadrada(string $bytes): string
    {
        $imagem = self::abrir($bytes);
        $jpeg = self::quadradaDe($imagem);
        imagedestroy($imagem);

        return $jpeg;
    }

    /** @throws InvalidArgumentException quando os bytes não são uma imagem que o GD abra */
    public static function inteira(string $bytes): string
    {
        $imagem = self::abrir($bytes);
        $jpeg = self::inteiraDe($imagem);
        imagedestroy($imagem);

        return $jpeg;
    }

    /*
    |--------------------------------------------------------------------------
    | Miolo
    |--------------------------------------------------------------------------
    */

    private static function abrir(string $bytes): GdImage
    {
        $imagem = self::semAvisos(fn () => imagecreatefromstring($bytes));

        if ($imagem === false) {
            throw new InvalidArgumentException('O arquivo não é uma imagem que dá para abrir (JPG, PNG ou WEBP).');
        }

        return self::corrigirOrientacao($imagem, $bytes);
    }

    /** Recorta o centro num quadrado e leva para LADO × LADO. */
    private static function quadradaDe(GdImage $imagem): string
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $lado = min($largura, $altura);
        $x = (int) floor(($largura - $lado) / 2);
        $y = (int) floor(($altura - $lado) / 2);

        $tela = self::tela(self::LADO, self::LADO);
        imagecopyresampled($tela, $imagem, 0, 0, $x, $y, self::LADO, self::LADO, $lado, $lado);

        return self::jpeg($tela);
    }

    /** A foto toda, cabendo num quadro de LADO_INTEIRA, sem ampliar. */
    private static function inteiraDe(GdImage $imagem): string
    {
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $escala = min(1, self::LADO_INTEIRA / max($largura, $altura));
        $novaLargura = max(1, (int) round($largura * $escala));
        $novaAltura = max(1, (int) round($altura * $escala));

        $tela = self::tela($novaLargura, $novaAltura);
        imagecopyresampled($tela, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);

        return self::jpeg($tela);
    }

    private static function tela(int $largura, int $altura): GdImage
    {
        $tela = imagecreatetruecolor($largura, $altura);

        // PNG com transparência: JPEG não tem alfa, e sem o fundo o que era
        // transparente viraria preto.
        imagefill($tela, 0, 0, imagecolorallocate($tela, 255, 255, 255));

        return $tela;
    }

    private static function jpeg(GdImage $tela): string
    {
        ob_start();
        imagejpeg($tela, null, self::QUALIDADE);
        $jpeg = ob_get_clean();

        imagedestroy($tela);

        return $jpeg;
    }

    /**
     * Gira conforme a tag EXIF Orientation.
     *
     * Foto de celular tirada em pé costuma vir "deitada" nos pixels, com a
     * tag dizendo "gire 90°". O navegador obedece; o GD não — e a derivada
     * apareceria virada. Sem a extensão exif, devolve como veio: nunca falha
     * por isso. Só JPEG carrega EXIF; para PNG/WEBP a leitura falha em
     * silêncio e nada muda.
     */
    private static function corrigirOrientacao(GdImage $imagem, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $imagem;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = self::semAvisos(fn () => exif_read_data($stream));
        fclose($stream);

        $orientacao = (int) ($exif['Orientation'] ?? 1);

        // imagerotate gira no sentido anti-horário para ângulos positivos.
        $graus = match ($orientacao) {
            3 => 180,
            6 => -90,   // EXIF 6: a câmera pede 90° horário
            8 => 90,    // EXIF 8: 90° anti-horário
            default => 0,
        };

        if ($graus === 0) {
            return $imagem;
        }

        $girada = imagerotate($imagem, $graus, 0);
        imagedestroy($imagem);

        return $girada;
    }

    /**
     * Roda a chamada engolindo os avisos do PHP.
     *
     * imagecreatefromstring() e exif_read_data() avisam quando o conteúdo não
     * é o que esperam — e "não é imagem" é um caso normal aqui, tratado pelo
     * retorno false, não um defeito para ir para o log. O `@` faria o mesmo,
     * mas o PHPUnit ainda registra o aviso silenciado; o handler não deixa
     * rastro.
     */
    private static function semAvisos(callable $chamada): mixed
    {
        set_error_handler(fn () => true);

        try {
            return $chamada();
        } finally {
            restore_error_handler();
        }
    }
}
