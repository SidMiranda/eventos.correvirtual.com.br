<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Grava as imagens de um evento no R2.
 *
 * Existe separado do controller porque o caminho é derivado do organizador e do
 * evento — nunca de nada que venha do formulário. Um caminho montado com dado
 * do navegador deixaria um organizador escrever na pasta de outro.
 *
 * O disco é o R2 e não o container de propósito: o container é recriado a cada
 * deploy, e a imagem que o organizador subiu iria junto.
 *
 * Ver docs/specs/armazenamento-r2.md.
 */
class ImagensDoEvento
{
    /**
     * A partir de que proporção a imagem serve como banner do topo.
     *
     * 2:1 é folgado de propósito: um banner de verdade costuma ser bem mais
     * largo (o primeiro recebido tem 5:1), e o cartaz da prova é retrato
     * (0,56:1). Qualquer corte entre os dois separa os casos com sobra.
     */
    public const PROPORCAO_DE_BANNER = 2.0;

    /**
     * Salva o banner e anota se ele serve para o topo da página.
     *
     * O campo sempre recebeu duas coisas diferentes: o cartaz da prova
     * (retrato) e um banner horizontal. O topo só usa a imagem quando ela é
     * larga — ver a migration `add_banner_wide_to_events_table`.
     */
    public static function salvarBanner(Event $event, UploadedFile $arquivo): void
    {
        self::salvar($event, $arquivo, 'banner');

        $event->banner_ratio = self::proporcao(file_get_contents($arquivo->getRealPath()));
        $event->save();
    }

    /**
     * A proporção da imagem (largura ÷ altura), ou null se não for legível.
     *
     * É o número que a página usa para desenhar o quadro do topo na medida
     * exata do banner, sem cortar as pontas.
     */
    public static function proporcao(string $bytes): ?float
    {
        $medidas = @getimagesizefromstring($bytes);

        if (! $medidas || $medidas[1] <= 0) {
            return null;
        }

        return round($medidas[0] / $medidas[1], 3);
    }

    /** A imagem é larga o bastante para virar banner do topo? */
    public static function ehLarga(string $bytes): bool
    {
        $proporcao = self::proporcao($bytes);

        return $proporcao !== null && $proporcao >= self::PROPORCAO_DE_BANNER;
    }

    /**
     * Salva o card (imagem da listagem).
     */
    public static function salvarCard(Event $event, UploadedFile $arquivo): void
    {
        self::salvar($event, $arquivo, 'card');
    }

    /**
     * Gera e grava a imagem do cartão de compartilhamento a partir da arte.
     *
     * É derivada, não enviada: quem cadastra manda uma arte só, e o cartão do
     * WhatsApp precisa de outro formato e outro peso (ver ImagemOg). Um
     * problema aqui não pode derrubar o cadastro do evento — a arte já foi
     * salva —, então a falha vira registro no log e o link cai no cartão
     * padrão da plataforma.
     */
    public static function gerarOg(Event $event, string $conteudoDaArte): void
    {
        try {
            ImagemPublica::salvarConteudo(
                self::caminho($event, 'og'),
                ImagemOg::gerar($conteudoDaArte),
                'image/jpeg'
            );
        } catch (\Throwable $e) {
            Log::error('Não consegui gerar a imagem de compartilhamento do evento', [
                'event_id' => $event->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private static function salvar(Event $event, UploadedFile $arquivo, string $tipo): void
    {
        // Sempre .jpg no destino, independente do que foi enviado: o caminho é
        // derivado (organizadores/X/eventos/Y/banner.jpg) e precisa ser
        // previsível para o site montar a URL sem consultar o banco.
        ImagemPublica::salvar(self::caminho($event, $tipo), $arquivo);
    }

    public static function apagar(Event $event): void
    {
        foreach (['banner', 'card', 'og'] as $tipo) {
            ImagemPublica::apagar(self::caminho($event, $tipo));
        }
    }

    /**
     * O caminho no bucket. Sai do organizador dono do evento e do id do evento,
     * nunca de entrada do usuário.
     */
    public static function caminho(Event $event, string $tipo): string
    {
        return "publico/organizadores/{$event->organizer_id}/eventos/{$event->id}/{$tipo}.jpg";
    }

    /** Regra de validação, compartilhada com os outros formulários com imagem. */
    public static function regraDeValidacao(): array
    {
        return ImagemPublica::regraDeValidacao();
    }
}
