<?php

namespace App\Support;

/**
 * Texto livre digitado no painel, pronto para sair no site.
 *
 * Três campos já usavam `nl2br(e(...))` solto na view (descrição, cronograma e
 * informações da inscrição do evento). Ao trazer o bloco "Sobre nós" para o
 * painel apareceu uma quarta regra possível — o texto que estava no Blade
 * tinha duas palavras em negrito —, e duas regras diferentes para a mesma
 * coisa é como nasce a divergência entre telas. Então a regra é uma só:
 *
 * 1. `e()` primeiro: o que veio do formulário é texto, nunca HTML.
 * 2. `**assim**` vira negrito. É a única marcação aceita, e ela é aplicada
 *    DEPOIS do escape — as tags saem daqui, não do que foi digitado.
 * 3. `nl2br` por último: a quebra de linha e a linha em branco que a pessoa
 *    digitou aparecem iguais na página.
 */
class TextoDoSite
{
    public static function paraHtml(?string $texto): string
    {
        if (blank($texto)) {
            return '';
        }

        $escapado = e($texto);

        // `+?` para parar no primeiro `**` seguinte, senão dois trechos em
        // negrito na mesma linha viram um só, engolindo o que houver no meio.
        // O `(?!\s)`/`(?<!\s)` exige conteúdo colado nos asteriscos, então
        // "2 ** 3 = 8" continua sendo multiplicação e não abre um negrito.
        $comNegrito = preg_replace('/\*\*(?!\s)(.+?)(?<!\s)\*\*/u', '<strong>$1</strong>', $escapado);

        return nl2br($comNegrito);
    }
}
