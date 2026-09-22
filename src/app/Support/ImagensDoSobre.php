<?php

namespace App\Support;

use App\Models\Organizer;
use Illuminate\Http\UploadedFile;

/**
 * A foto do bloco "Sobre nós", no bucket público do R2.
 *
 * Caminho fixo por organizador — a foto é uma só, e trocar significa gravar
 * por cima. O cache do CDN é contornado pela versão na URL (ver
 * Arquivos::sobreNosDoOrganizador), não por um nome de arquivo novo: nome
 * previsível deixa a foto acessível mesmo se a linha do banco se perder.
 *
 * Como em todo o resto, o caminho vem do id do organizador e nunca do nome do
 * arquivo enviado — caminho montado com dado do navegador deixaria um
 * organizador escrever na pasta de outro.
 */
class ImagensDoSobre
{
    public static function salvar(Organizer $organizador, UploadedFile $arquivo): void
    {
        ImagemPublica::salvar(self::caminho($organizador), $arquivo);
    }

    public static function apagar(Organizer $organizador): void
    {
        ImagemPublica::apagar(self::caminho($organizador));
    }

    public static function caminho(Organizer $organizador): string
    {
        return "publico/organizadores/{$organizador->id}/sobre-nos.jpg";
    }

    public static function regraDeValidacao(): array
    {
        return ImagemPublica::regraDeValidacao();
    }
}
