@extends('layouts.auth')

@section('title','Registrar')

@section('content')

<style>
    /* Só a lista de sugestões da cidade; o resto do formulário é o forms.css.
       Inline porque esta tela ainda está no CSS antigo, pendente do redesign
       (backlog) — não vale abrir arquivo novo para isso. */
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

<div class="modal-overlay" style="display:flex">
<div class="modal">

<form method="POST" action="{{ route('register') }}" class="form-container">

@csrf

<h2>Registrar</h2>

<input 
type="text"
name="name"
placeholder="Nome completo"
value="{{ old('name') }}"
required
>

<input 
type="date"
name="birth_date"
value="{{ old('birth_date') }}"
required
>

<select name="sex" required>

<option value="">Sexo</option>

<option value="male">
Masculino
</option>

<option value="female">
Feminino
</option>

<option value="other">
Outro
</option>

</select>

{{-- Cidade: texto visível + o id escondido, que é o que vai para o banco.
     Quem digita e não escolhe da lista não passa na validação — assim o que
     foi digitado não se perde em silêncio, e a cidade fica vinculada de
     verdade ao município do IBGE. --}}
<div class="cidade-campo">
    <input
    type="text"
    name="cidade"
    id="campoCidade"
    autocomplete="off"
    maxlength="120"
    placeholder="Cidade"
    value="{{ old('cidade') }}"
    >
    <input type="hidden" name="city_id" id="campoCidadeId" value="{{ old('city_id') }}">
    <ul class="cidade-lista" id="listaCidades" hidden></ul>
</div>

<input 
type="text"
name="phone"
placeholder="Celular"
value="{{ old('phone') }}"
required
>

<input 
type="email"
name="email"
placeholder="Email"
value="{{ old('email') }}"
required
>

<input 
type="text"
name="cpf"
placeholder="CPF"
value="{{ old('cpf') }}"
required
>

<input 
type="password"
name="password"
placeholder="Senha"
required
>

<button type="submit" class="btn-primary">
Registrar
</button>

<div class="form-links">

<a href="{{ route('login') }}">
Já tenho conta
</a>

</div>

</form>

</div>
</div>

@endsection

@push('scripts')
<script>
    const cpfInput = document.querySelector('input[name="cpf"]');
    if(cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ""); // Remove tudo que não for número
            if (value.length > 11) value = value.substring(0, 11); // Limita a 11 dígitos
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
            e.target.value = value;
        });
    }

    /* ------------------------------------------------------------------
       Cidade: busca no nosso banco a partir de 3 letras.

       O texto que a pessoa vê e o `city_id` que vai para o banco são campos
       separados: qualquer digitação depois de escolher limpa o id, senão
       daria para escolher "Mogi Guaçu", apagar tudo, escrever outra coisa e
       enviar o id antigo junto.
       ------------------------------------------------------------------ */
    (function () {
        var campo = document.getElementById('campoCidade');
        var campoId = document.getElementById('campoCidadeId');
        var lista = document.getElementById('listaCidades');
        if (!campo || !campoId || !lista) { return; }

        var MINIMO = 3;
        var url = @json(route('cidades.buscar'));
        var espera = null;
        var atual = -1;
        var opcoes = [];

        function fechar() {
            lista.hidden = true;
            lista.innerHTML = '';
            atual = -1;
            opcoes = [];
        }

        function escolher(opcao) {
            campo.value = opcao.nome;
            campoId.value = opcao.id;
            fechar();
        }

        function desenhar(cidades) {
            lista.innerHTML = '';
            opcoes = cidades;

            if (!cidades.length) {
                var vazio = document.createElement('li');
                vazio.className = 'cidade-vazia';
                vazio.textContent = 'Nenhuma cidade encontrada.';
                lista.appendChild(vazio);
                lista.hidden = false;
                return;
            }

            cidades.forEach(function (cidade, i) {
                var item = document.createElement('li');
                item.textContent = cidade.nome;
                item.setAttribute('role', 'option');
                item.addEventListener('mousedown', function (e) {
                    /* mousedown e não click: o blur do campo fecharia a lista
                       antes de o clique chegar. */
                    e.preventDefault();
                    escolher(cidade);
                });
                lista.appendChild(item);
                if (i === atual) { item.setAttribute('aria-selected', 'true'); }
            });

            lista.hidden = false;
        }

        function marcar(novo) {
            var itens = lista.querySelectorAll('li[role="option"]');
            if (!itens.length) { return; }
            if (atual >= 0 && itens[atual]) { itens[atual].removeAttribute('aria-selected'); }
            atual = (novo + itens.length) % itens.length;
            itens[atual].setAttribute('aria-selected', 'true');
            itens[atual].scrollIntoView({ block: 'nearest' });
        }

        function buscar() {
            var termo = campo.value.trim();
            if (termo.length < MINIMO) { fechar(); return; }

            fetch(url + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(desenhar)
                .catch(fechar);
        }

        campo.addEventListener('input', function () {
            /* Mexeu no texto, o vínculo anterior deixa de valer. */
            campoId.value = '';
            clearTimeout(espera);
            espera = setTimeout(buscar, 250);
        });

        campo.addEventListener('keydown', function (e) {
            if (lista.hidden) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); marcar(atual + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(atual - 1); }
            else if (e.key === 'Enter' && atual >= 0 && opcoes[atual]) { e.preventDefault(); escolher(opcoes[atual]); }
            else if (e.key === 'Escape') { fechar(); }
        });

        campo.addEventListener('blur', function () { setTimeout(fechar, 120); });
    })();

    const phoneInput = document.querySelector('input[name="phone"]');
    if(phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ""); // Remove tudo que não for número
            if (value.length > 11) value = value.substring(0, 11); // Limita a 11 dígitos (DDD + 9 dígitos)
            value = value.replace(/^(\d{2})(\d)/g, "($1) $2"); // Coloca parênteses no DDD
            value = value.replace(/(\d)(\d{4})$/, "$1-$2"); // Coloca o hífen antes dos últimos 4 dígitos
            e.target.value = value;
        });
    }
</script>
@endpush