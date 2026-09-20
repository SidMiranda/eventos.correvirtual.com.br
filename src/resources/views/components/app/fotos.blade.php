@props(['fotos'])

{{--
    A galeria de fotos da home — a faixa no estilo do feed do Instagram.

    Fica FORA do .container de propósito: é a única seção da home a 100% da
    largura. Quem decide quantas colunas e quantas fotos aparecem é o CSS
    (.fotos em home-v2.css): 6×2 no desktop, 2×3 no celular, sem vão entre
    elas. A home manda 12; da 7ª em diante o celular esconde.

    Cada quadrado mostra o recorte central da foto, preenchendo — as fotos
    chegam de proporções variadas, e é isso que deixa a grade regular. No
    hover, a foto cresce um pouco dentro do quadrado. (A derivada com a foto
    inteira existe no bucket, `{id}-inteira.jpg`, para um "ver inteira" no
    futuro; a faixa não a usa.)

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
                $alt = $foto->caption ?: 'Foto da galeria';
            @endphp

            @if ($foto->link_url)
                <a class="foto foto--link" href="{{ $foto->link_url }}"
                   target="_blank" rel="noopener" title="{{ $foto->caption }}">
                    <img class="foto__quadrada" src="{{ $quadrada }}" alt="{{ $alt }}" loading="lazy">
                </a>
            @else
                <figure class="foto" title="{{ $foto->caption }}">
                    <img class="foto__quadrada" src="{{ $quadrada }}" alt="{{ $alt }}" loading="lazy">
                </figure>
            @endif
        @endforeach
    </div>
</section>
