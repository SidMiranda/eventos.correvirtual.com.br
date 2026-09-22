<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\Photo;
use App\Models\Sponsor;
use App\Models\Team;

/**
 * O único lugar que sabe montar URL de imagem pública.
 *
 * Antes disso, três views (`event-card`, `my-subscriptions`, `main-banner`)
 * montavam o caminho na mão e decidiam o fallback com
 * `file_exists(public_path(...))`. Isso tinha dois problemas:
 *
 * 1. A regra estava copiada em três lugares, com pequenas diferenças.
 * 2. `file_exists` olha o disco do container. Com a imagem no R2, ele responde
 *    `false` sempre — e o site não quebra, só passa a mostrar o fallback em
 *    TODA imagem, em silêncio. Falha silenciosa é pior que erro.
 *
 * Aqui a existência da imagem é decidida pelo BANCO (`events.banner_url`
 * preenchido), não pelo disco, e o `onerror` do <img> cobre o caso de o
 * arquivo estar faltando de verdade. Funciona igual no disco local e no CDN.
 *
 * Ver docs/specs/armazenamento-r2.md.
 */
class Arquivos
{
    /**
     * Prefixo das URLs públicas. Sem CDN configurado, aponta para
     * `public/images` do próprio container — que tem exatamente a mesma
     * estrutura de pastas do bucket.
     */
    public static function base(): string
    {
        $cdn = config('arquivos.base_url');

        return $cdn !== '' ? $cdn : rtrim(asset('images'), '/');
    }

    public static function url(string $caminhoRelativo): string
    {
        return self::base() . '/' . ltrim($caminhoRelativo, '/');
    }

    /*
    |--------------------------------------------------------------------------
    | Imagens de evento
    |--------------------------------------------------------------------------
    */

    /** Imagem retangular usada nos cards de listagem. */
    public static function cardDoEvento(Event $event): string
    {
        return $event->banner_url
            ? self::comVersao(self::url("organizadores/{$event->organizer_id}/eventos/{$event->id}/card.jpg"), $event)
            : self::cardPadrao();
    }

    /** Imagem grande, no topo da página do evento. */
    public static function bannerDoEvento(Event $event): string
    {
        return $event->banner_url
            ? self::comVersao(self::url("organizadores/{$event->organizer_id}/eventos/{$event->id}/banner.jpg"), $event)
            : self::bannerPadrao();
    }

    /**
     * Acrescenta ?v={updated_at} à URL da imagem.
     *
     * O caminho no bucket é derivado do id e não muda quando a imagem é
     * trocada — e ela sobe com `Cache-Control: immutable`, então o CDN guarda a
     * versão antiga por um ano. Sem este parâmetro, trocar a arte de um evento
     * não aparece para ninguém.
     *
     * Foi assim que a primeira carga de eventos saiu com as artes trocadas: o
     * ambiente de desenvolvimento havia escrito nos mesmos caminhos antes, e o
     * CDN continuou servindo aquelas imagens.
     */
    private static function comVersao(string $url, Event $event): string
    {
        $versao = $event->updated_at?->timestamp;

        return $versao ? "{$url}?v={$versao}" : $url;
    }

    /**
     * Imagem do cartão de pré-visualização do link (WhatsApp, Facebook).
     *
     * É uma terceira derivada da arte, deitada e leve — mandar o cartaz cru
     * fazia o robô recortar o meio e, pelo peso, muitas vezes desistir da
     * prévia. Ver App\Support\ImagemOg.
     */
    public static function ogDoEvento(Event $event): string
    {
        return $event->banner_url
            ? self::comVersao(self::url("organizadores/{$event->organizer_id}/eventos/{$event->id}/og.jpg"), $event)
            : self::ogDoOrganizador($event->organizer_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Imagens do organizador
    |--------------------------------------------------------------------------
    */

    /**
     * Aceita nulo: em admin.correvirtual.com.br não existe organizador no
     * domínio, e a tela de login é a mesma view pública. Sem esse caso, o
     * favicon montaria `organizadores//logo.png`.
     */
    public static function logoDoOrganizador(?int $organizerId): ?string
    {
        return $organizerId ? self::url("organizadores/{$organizerId}/logo.png") : null;
    }

    public static function bannerDoOrganizador(int $organizerId): string
    {
        return self::url("organizadores/{$organizerId}/banner.jpg");
    }

    public static function bannerMobileDoOrganizador(int $organizerId): string
    {
        return self::url("organizadores/{$organizerId}/banner-mobile.jpg");
    }

    /**
     * A foto do bloco "Sobre nós".
     *
     * Aceita o organizador inteiro para poder acrescentar `?v={updated_at}`: o
     * caminho no bucket é fixo e a gravação usa `Cache-Control: immutable`,
     * então sem a versão a foto trocada pelo painel continuaria mostrando a
     * antiga por um ano no CDN. Passar só o id ainda funciona (é o que o
     * `$organizerId` compartilhado nas views tem), só não leva a versão.
     */
    public static function sobreNosDoOrganizador(Organizer|int $organizador): string
    {
        $id = $organizador instanceof Organizer ? $organizador->id : $organizador;
        $url = self::url("organizadores/{$id}/sobre-nos.jpg");

        $versao = $organizador instanceof Organizer ? $organizador->updated_at?->timestamp : null;

        return $versao ? "{$url}?v={$versao}" : $url;
    }

    /**
     * Cartão de compartilhamento das páginas que não são de um evento (home,
     * inscrição, login). Também 1200x630 — ver o comentário em ogDoEvento.
     */
    public static function ogDoOrganizador(int $organizerId): string
    {
        return self::url("organizadores/{$organizerId}/og.jpg");
    }

    /**
     * O organizador tem imagem de marca própria?
     *
     * Diferente do evento, o organizador não tem no banco nenhum campo dizendo
     * quais imagens ele possui — o código sempre olhou o disco. Enquanto for
     * disco, dá pra continuar olhando; com CDN, não dá (seria uma requisição
     * de rede por página), então assume-se que existe e o `onerror` do <img>
     * cobre a falta.
     *
     * Consequência a assumir de olhos abertos: com CDN ligado, um organizador
     * sem banner não mostra mais a faixa com nome/e-mail que aparecia no lugar.
     * A correção de verdade é dar ao organizador campos de imagem como o evento
     * tem — está registrado em docs/specs/armazenamento-r2.md.
     */
    public static function organizadorTem(string $caminhoRelativo): bool
    {
        if (config('arquivos.base_url') !== '') {
            return true;
        }

        return file_exists(public_path('images/' . ltrim($caminhoRelativo, '/')));
    }

    /**
     * Arte de uma prova já realizada, na vitrine da home.
     *
     * Diferente das imagens de evento, aqui o nome do arquivo vem de
     * `config/galeria.php` — arquivo do projeto, não entrada de usuário. Ainda
     * assim só o nome base é aproveitado, para que uma edição descuidada da
     * config não consiga montar caminho para fora da pasta do organizador.
     */
    public static function arteRealizada(int $organizerId, string $arquivo): string
    {
        return self::url("organizadores/{$organizerId}/realizados/" . basename($arquivo));
    }

    /*
    |--------------------------------------------------------------------------
    | Brasão da equipe
    |--------------------------------------------------------------------------
    */

    /**
     * Devolve nulo quando a equipe não tem brasão, para a tela decidir o que
     * mostrar no lugar (as iniciais) em vez de exibir imagem quebrada.
     */
    public static function brasaoDaEquipe(Team $team): ?string
    {
        return $team->has_logo
            ? self::url("organizadores/{$team->organizer_id}/equipes/{$team->id}/brasao.jpg")
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Logo do patrocinador
    |--------------------------------------------------------------------------
    */

    /**
     * Devolve nulo quando o patrocinador não tem logo, para a tela decidir o
     * que mostrar no lugar (o nome) em vez de exibir imagem quebrada.
     */
    public static function logoDoPatrocinador(Sponsor $sponsor): ?string
    {
        if (! $sponsor->has_logo) {
            return null;
        }

        $url = self::url("organizadores/{$sponsor->organizer_id}/patrocinadores/{$sponsor->id}/logo.png");
        $versao = $sponsor->updated_at?->timestamp;

        // O caminho é derivado do id e não muda quando o logo é trocado, e ele
        // sobe com Cache-Control: immutable — sem a versão, a troca não
        // apareceria para ninguém. Mesmo cuidado da arte do evento.
        return $versao ? "{$url}?v={$versao}" : $url;
    }

    /*
    |--------------------------------------------------------------------------
    | Foto da galeria da home
    |--------------------------------------------------------------------------
    */

    /**
     * Sempre existe: a linha só nasce com a imagem gravada (ver
     * App\Support\ImagensDaFoto). A versão na URL é o updated_at, que o
     * painel força ao trocar a imagem — o caminho é o mesmo e o CDN guarda a
     * anterior.
     */
    public static function fotoDaGaleria(Photo $photo): string
    {
        return self::comVersaoDaFoto(self::url("organizadores/{$photo->organizer_id}/fotos/{$photo->id}.jpg"), $photo);
    }

    /** A foto toda, para o hover da grade. */
    public static function fotoInteiraDaGaleria(Photo $photo): string
    {
        return self::comVersaoDaFoto(self::url("organizadores/{$photo->organizer_id}/fotos/{$photo->id}-inteira.jpg"), $photo);
    }

    private static function comVersaoDaFoto(string $url, Photo $photo): string
    {
        $versao = $photo->updated_at?->timestamp;

        return $versao ? "{$url}?v={$versao}" : $url;
    }

    /*
    |--------------------------------------------------------------------------
    | Imagens da plataforma (não pertencem a organizador nenhum)
    |--------------------------------------------------------------------------
    */

    public static function imagemDaHome(string $arquivo): string
    {
        return self::url("plataforma/home/{$arquivo}");
    }

    public static function cardPadrao(): string
    {
        return self::url('plataforma/padrao/card.jpg');
    }

    public static function bannerPadrao(): string
    {
        return self::url('plataforma/padrao/banner.jpg');
    }

    /** Último recurso do cartão de compartilhamento. Também 1200x630. */
    public static function ogPadrao(): string
    {
        return self::url('plataforma/padrao/og.jpg');
    }

    public static function usuarioPadrao(): string
    {
        return self::url('plataforma/padrao/user.jpg');
    }
}
