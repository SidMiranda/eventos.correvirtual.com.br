@props(['fotos'])

{{--
    A galeria de fotos da home — a faixa no estilo do feed do Instagram.

    Fica FORA do .container de propósito: é a única seção da home a 100% da
    largura. Quem decide quantas colunas e quantas fotos aparecem é o CSS
    (.fotos em home-v2.css): 6×2 no desktop, 2×3 no celular. A home manda
    12; da 7ª em diante o celular esconde.

    Cada quadrado tem duas imagens: o recorte (o que se vê) e a foto inteira,
    que aparece em fade no hover, centralizada sobre o fundo escuro — as fotos
    chegam de proporções variadas, e o recorte pelo centro é o que deixa a
    grade regular. A inteira só é baixada no primeiro hover (data-src +
    script no fim): 12 a mais na carga da página seria o dobro do peso à toa.

    Foto com link abre em aba nova (rel="noopener": sem isso a página aberta
    ganha acesso a esta pela window.opener). Sem link, é só a imagem. A
    legenda não aparece escrita — é um feed — mas vira alt e title.
    Cadastro em /admin/fotos. Ver docs/specs/galeria-de-fotos.md.
--}}

<section class="fotos-secao" id="fotos">
    <h2 class="block-header-title">
        GALERIA <span> DE FOTOS </span>
    </h2>

    <div class="fotos">
        @foreach ($fotos as $foto)
            @php
                $quadrada = \App\Support\Arquivos::fotoDaGaleria($foto);
                $inteira = \App\Support\Arquivos::fotoInteiraDaGaleria($foto);
                $alt = $foto->caption ?: 'Foto da galeria';
            @endphp

            @if ($foto->link_url)
                <a class="foto foto--link" href="{{ $foto->link_url }}"
                   target="_blank" rel="noopener" title="{{ $foto->caption }}">
                    <img class="foto__quadrada" src="{{ $quadrada }}" alt="{{ $alt }}" loading="lazy">
                    <img class="foto__inteira" data-src="{{ $inteira }}" alt="" aria-hidden="true">
                </a>
            @else
                <figure class="foto" title="{{ $foto->caption }}">
                    <img class="foto__quadrada" src="{{ $quadrada }}" alt="{{ $alt }}" loading="lazy">
                    <img class="foto__inteira" data-src="{{ $inteira }}" alt="" aria-hidden="true">
                </figure>
            @endif
        @endforeach
    </div>
</section>

<script>
(function () {
    // A foto inteira só desce no primeiro hover (ou foco, ou toque).
    document.querySelectorAll('#fotos .foto').forEach(function (foto) {
        var inteira = foto.querySelector('.foto__inteira');
        if (!inteira) { return; }

        var carregar = function () {
            if (!inteira.getAttribute('src')) {
                inteira.setAttribute('src', inteira.getAttribute('data-src'));
            }
        };

        foto.addEventListener('mouseenter', carregar, { passive: true });
        foto.addEventListener('touchstart', carregar, { passive: true });
        foto.addEventListener('focus', carregar, true);
    });
})();
</script>
