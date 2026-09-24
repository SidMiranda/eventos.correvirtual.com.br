@extends('layouts.auth')

@section('title', 'Inscrição')

@section('content')

    <style>
        /* Só o que o forms.css não cobre. Fica inline porque a página ainda
           está no CSS antigo, pendente do redesign (backlog). */
        .cupom-linha { display: flex; gap: 8px; width: 100%; }
        .cupom-linha input[type="text"] { flex: 1; margin-bottom: 0; text-transform: uppercase; }
        #campoEquipe { text-transform: uppercase; }
        .cupom-linha button {
            width: auto; margin-top: 0; padding: 12px 18px; white-space: nowrap;
            background: #fff; color: #2e7d32; border: 1px solid #2e7d32;
        }
        .cupom-linha button:hover { background: #eaf4ec; }
        .cupom-dica { font-size: 13px; color: #666; margin: 6px 0 0; width: 100%; }
        /* forms.css estiliza select/p com display próprio: o [hidden] precisa vencer. */
        #campoCamiseta[hidden], #dicaKit[hidden], #resumo[hidden] { display: none !important; }
        .previa-cupom {
            width: 100%; box-sizing: border-box; padding: 10px 14px; border-radius: 6px;
            font-size: 14px; line-height: 1.5; text-align: left; margin-bottom: 12px;
        }
        .previa-cupom--ok    { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .previa-cupom--erro  { background: #fdecea; color: #b3261e; border: 1px solid #f5c2c0; }
        .previa-cupom--aviso { background: #fff8e1; color: #7a5a00; border: 1px solid #ffe08a; }
        .previa-cupom strong { display: block; margin-top: 6px; font-size: 16px; }
        .lote-atual { font-size: 13px; color: #475569; margin: 0 0 10px; width: 100%; }
    </style>

    <div class="modal-overlay">

        <div class="modal text-left">

            <form method="POST" action="/subscribe/event/{{ $event->id }}" class="form-container" id="formInscricao">

                @csrf

                <h2 class="event-title">
                    {{ $event->title }}
                </h2>

                <div class="athlete-info">
                    <span>👤 Atleta:</span>
                    <strong>{{ Auth::user()->name }}</strong>
                </div>
                <p>
                    <hr>
                </p>

                {{-- O lote vigente é resolvido pelo servidor (Event::loteVigente);
                     os valores abaixo são os dele. --}}
                <p class="lote-atual">Inscrições no <strong>{{ $dados['lote']['nome'] }}</strong>.</p>

                <select name="modality_id" id="campoModalidade" required>
                    <option value="">Modalidade</option>
                    @foreach ($dados['modalidades'] as $modalidade)
                        <option value="{{ $modalidade['id'] }}" @selected(old('modality_id') == $modalidade['id'])>
                            {{ $modalidade['nome'] }}
                        </option>
                    @endforeach
                </select>

                {{-- Preenchido pelo JavaScript com os kits DA modalidade escolhida,
                     cada um com o preço da grade neste lote. O servidor confere
                     de novo no envio: o vínculo kit↔modalidade e o preço são
                     dele, não do navegador. --}}
                <select name="kit_id" id="campoKit" required disabled>
                    <option value="">Kit</option>
                </select>
                <p class="cupom-dica" id="dicaKit" hidden></p>

                {{-- Só aparece quando o kit escolhido oferece tamanhos — e aí é
                     obrigatório. Kit sem camiseta não pergunta. --}}
                <select name="camiseta" id="campoCamiseta" hidden>
                    <option value="">Tamanho da camiseta</option>
                </select>

                <div class="cupom-linha">
                    <input type="text" name="cupom" id="campoCupom" maxlength="20"
                           placeholder="Tem um CUPOM?" autocomplete="off" spellcheck="false"
                           value="{{ old('cupom') }}">
                    <button type="button" id="aplicarCupom">Aplicar</button>
                </div>
                <p class="cupom-dica">Escolha o kit, digite o código e aplique para ver o valor com desconto.</p>

                {{-- O resumo: lote, preço, desconto de idade (vem do cadastro, sem
                     perguntar), cupom e total. É conveniência — o envio refaz a
                     conta no servidor. --}}
                <div class="previa-cupom" id="resumo" hidden></div>

                {{-- Equipe: texto livre por ora (decisão do dono em 2026-09-22). --}}
                <input type="text" name="equipe" id="campoEquipe" maxlength="50"
                       placeholder="Equipe (opcional)" autocomplete="off" spellcheck="false"
                       value="{{ old('equipe') }}">
                <p class="cupom-dica">Corre por alguma assessoria ou grupo? Escreva o nome — letras, números e espaços, até 50 caracteres.</p>

                <button type="submit" class="btn-primary">
                    Confirmar inscrição
                </button>

            </form>

        </div>

    </div>

    <script type="application/json" id="dadosDaInscricao">{!! json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

@endsection

@push('scripts')
<script>
(function () {
    var dados = JSON.parse(document.getElementById('dadosDaInscricao').textContent);

    var form = document.getElementById('formInscricao');
    var modalidade = document.getElementById('campoModalidade');
    var kit = document.getElementById('campoKit');
    var camiseta = document.getElementById('campoCamiseta');
    var dicaKit = document.getElementById('dicaKit');
    var cupom = document.getElementById('campoCupom');
    var botao = document.getElementById('aplicarCupom');
    var resumo = document.getElementById('resumo');
    var url = @json(route('subscribe.cotacao', $event->id));
    // O <head> público não tem meta csrf; o token do próprio form serve.
    var token = form.querySelector('input[name="_token"]').value;

    // Voltou do servidor com erro: o que estava escolhido volta a ficar escolhido.
    var kitAnterior = @json(old('kit_id'));
    var camisetaAnterior = @json(old('camiseta'));

    function opcao(valor, rotulo) {
        var o = document.createElement('option');
        o.value = valor;
        o.textContent = rotulo;
        return o;
    }

    function kitsDaModalidade() {
        var m = dados.modalidades.find(function (x) { return String(x.id) === modalidade.value; });
        return m ? m.kits : [];
    }

    function kitEscolhido() {
        return kitsDaModalidade().find(function (k) { return String(k.id) === kit.value; }) || null;
    }

    function montarKits(selecionar) {
        kit.innerHTML = '';
        kit.appendChild(opcao('', 'Kit'));
        kitsDaModalidade().forEach(function (k) { kit.appendChild(opcao(k.id, k.nome + ' — ' + k.preco)); });
        kit.disabled = !modalidade.value;
        if (selecionar && kitsDaModalidade().some(function (k) { return String(k.id) === String(selecionar); })) {
            kit.value = String(selecionar);
        }
    }

    function montarTamanhos(selecionar) {
        var k = kitEscolhido();
        var tem = !!(k && k.tamanhos.length);

        camiseta.innerHTML = '';
        camiseta.appendChild(opcao('', 'Tamanho da camiseta'));
        if (tem) { k.tamanhos.forEach(function (t) { camiseta.appendChild(opcao(t.codigo, t.rotulo)); }); }

        camiseta.hidden = !tem;
        camiseta.required = tem;
        if (tem && selecionar) { camiseta.value = selecionar; }
    }

    function mostrarDica() {
        var k = kitEscolhido();
        dicaKit.textContent = k && k.descricao ? k.descricao : '';
        dicaKit.hidden = !dicaKit.textContent;
    }

    function mostrar(html, tipo) {
        resumo.innerHTML = html;
        resumo.className = 'previa-cupom previa-cupom--' + tipo;
        resumo.hidden = false;
    }

    function esconder() { resumo.hidden = true; resumo.innerHTML = ''; }

    function texto(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // Trocar de kit rápido dispara duas cotações; a do kit anterior pode
    // chegar depois e sobrescrever o resumo. Só a última pedida desenha.
    var pedido = 0;

    function cotar() {
        if (!modalidade.value || !kit.value) { esconder(); return; }

        var meu = ++pedido;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ modality_id: modalidade.value, kit_id: kit.value, cupom: cupom.value.trim() })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (meu !== pedido) { return; }
            var d = res.d || {};
            if (!res.ok || !d.ok) {
                mostrar(texto(d.mensagem || 'Não foi possível calcular o valor.'), 'erro');
                return;
            }
            var linhas = [texto(d.lote) + ': ' + texto(d.bruto)];
            if (d.tem_idade) { linhas.push(texto(d.categoria) + ': −' + texto(d.desconto_idade)); }
            if (d.tem_cupom) { linhas.push('Cupom ' + texto(d.cupom) + ': −' + texto(d.desconto_cupom)); }
            var total = d.gratuita ? 'Total: R$ 0,00 — sem pagamento.' : 'Total: ' + texto(d.liquido);
            mostrar(linhas.join('<br>') + '<strong>' + total + '</strong>', 'ok');
        })
        .catch(function () {
            if (meu !== pedido) { return; }
            mostrar('Não deu para calcular agora. Você pode enviar mesmo assim — o servidor confere tudo de novo.', 'aviso');
        });
    }

    modalidade.addEventListener('change', function () { montarKits(null); montarTamanhos(null); mostrarDica(); cotar(); });
    kit.addEventListener('change', function () { montarTamanhos(null); mostrarDica(); cotar(); });
    botao.addEventListener('click', cotar);

    // Enter no campo aplica, em vez de enviar o formulário inteiro.
    cupom.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); cotar(); } });
    cupom.addEventListener('input', function () { cupom.value = cupom.value.toUpperCase(); });

    var equipe = document.getElementById('campoEquipe');
    if (equipe) { equipe.addEventListener('input', function () { equipe.value = equipe.value.toUpperCase(); }); }

    // Estado inicial — inclusive o que voltou do servidor com old().
    montarKits(kitAnterior);
    montarTamanhos(camisetaAnterior);
    mostrarDica();
    cotar();
})();
</script>
@endpush
