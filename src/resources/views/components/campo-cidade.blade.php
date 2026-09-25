@props(['texto' => '', 'cidadeId' => ''])

{{-- Cidade: texto visível + o id escondido, que é o que vai para o banco.
     Quem digita e não escolhe da lista não passa na validação — assim o que
     foi digitado não se perde em silêncio, e a cidade fica vinculada de
     verdade ao município do IBGE.

     Usado no cadastro e em "Minha conta" › Perfil. Traz o próprio estilo e o
     script (public/js/campo-cidade.js), uma vez por página. --}}
@once
    <style>
        .cidade-campo { position: relative; width: 100%; }
        .cidade-lista {
            position: absolute; z-index: 20; left: 0; right: 0; top: 100%;
            margin: -8px 0 0; padding: 0; list-style: none; text-align: left;
            background: #fff; border: 1px solid #ccc; border-radius: 6px;
            max-height: 240px; overflow-y: auto;
            box-shadow: 0 6px 18px rgba(0, 0, 0, .12);
        }
        .cidade-lista li { padding: 10px 14px; cursor: pointer; font-size: 15px; }
        .cidade-lista li:hover, .cidade-lista li[aria-selected="true"] { background: #eaf4ec; }
        .cidade-lista li.cidade-vazia { color: #666; cursor: default; }
        .cidade-lista li.cidade-vazia:hover { background: #fff; }
    </style>
    <script src="{{ asset('js/campo-cidade.js') }}?v={{ filemtime(public_path('js/campo-cidade.js')) }}" defer></script>
@endonce

<div class="cidade-campo">
    <input
        type="text"
        name="cidade"
        id="campoCidade"
        autocomplete="off"
        maxlength="120"
        placeholder="Cidade"
        value="{{ $texto }}"
        data-busca="{{ route('cidades.buscar') }}"
        {{ $attributes->merge(['required' => true]) }}
    >
    <input type="hidden" name="city_id" id="campoCidadeId" value="{{ $cidadeId }}">
    <ul class="cidade-lista" id="listaCidades" hidden></ul>
</div>
