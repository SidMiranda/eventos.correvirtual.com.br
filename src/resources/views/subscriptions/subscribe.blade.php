@extends('layouts.auth')

@section('title', 'Inscrição')

@section('content')

    <style>
        /* Só o que o forms.css não cobre: a linha do cupom e a caixa da prévia.
           Fica inline porque a página ainda está no CSS antigo, pendente do
           redesign (backlog) — não vale abrir arquivo novo para isso. */
        .cupom-linha { display: flex; gap: 8px; width: 100%; }
        .cupom-linha input[type="text"] { flex: 1; margin-bottom: 0; text-transform: uppercase; }
        #campoEquipe { text-transform: uppercase; }
        .cupom-linha button {
            width: auto; margin-top: 0; padding: 12px 18px; white-space: nowrap;
            background: #fff; color: #2e7d32; border: 1px solid #2e7d32;
        }
        .cupom-linha button:hover { background: #eaf4ec; }
        .cupom-dica { font-size: 13px; color: #666; margin: 6px 0 0; width: 100%; }
        .previa-cupom {
            width: 100%; box-sizing: border-box; padding: 10px 14px; border-radius: 6px;
            font-size: 14px; line-height: 1.4; text-align: left;
        }
        .previa-cupom--ok    { background: #e8f5e9; color: #1b5e20; border: 1px solid #a5d6a7; }
        .previa-cupom--erro  { background: #fdecea; color: #b3261e; border: 1px solid #f5c2c0; }
        .previa-cupom--aviso { background: #fff8e1; color: #7a5a00; border: 1px solid #ffe08a; }
        .previa-cupom strong { display: block; margin-top: 4px; }
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
                <select name="modality_id" required>

                    <option value="">Modalidade</option>
                    @foreach($event->modalities as $modality)
                        <option value="{{ $modality->id }}" @selected(old('modality_id') == $modality->id)>
                            {{ $modality->name }}
                        </option>
                    @endforeach
                </select>

                {{-- O preço aparece na opção: é o valor que o cupom vai abater,
                     e o atleta precisa ver de onde a conta parte. --}}
                <select name="kit_id" id="kitEscolhido" required>

                    <option value="">Kit</option>
                    @foreach($event->kits as $kit)
                        <option value="{{ $kit->id }}" @selected(old('kit_id') == $kit->id)>
                            {{ $kit->name }} — R$ {{ number_format($kit->price, 2, ',', '.') }}
                        </option>
                    @endforeach
                </select>

                <div class="cupom-linha">
                    <input type="text" name="cupom" id="campoCupom" maxlength="20"
                           placeholder="Cupom de desconto (opcional)" autocomplete="off" spellcheck="false"
                           value="{{ old('cupom') }}">
                    <button type="button" id="aplicarCupom">Aplicar</button>
                </div>
                <p class="cupom-dica">Tem um cupom? Escolha o kit, digite o código e aplique para ver o valor com desconto.</p>

                {{-- A prévia é conveniência: o servidor confere tudo de novo no envio. --}}
                <div class="previa-cupom" id="previaCupom" hidden></div>

                {{-- Equipe: texto livre por ora (decisão do dono em 2026-09-22).
                     Existe cadastro de equipes no painel desde 2026-08-29, mas
                     na primeira prova ninguém sabe ainda quais assessorias vão
                     aparecer — pedir que o atleta escolha de uma lista vazia
                     seria pior. Gravado em CAIXA ALTA para "corre mogi" e
                     "CORRE MOGI" não virarem duas equipes na hora de contar. --}}
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

@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('formInscricao');
    var kit = document.getElementById('kitEscolhido');
    var cupom = document.getElementById('campoCupom');
    var botao = document.getElementById('aplicarCupom');
    var caixa = document.getElementById('previaCupom');
    var url = @json(route('subscribe.cupom', $event->id));
    // O <head> público não tem meta csrf; o token do próprio form serve.
    var token = form.querySelector('input[name="_token"]').value;

    function mostrar(html, tipo) {
        caixa.innerHTML = html;
        caixa.className = 'previa-cupom previa-cupom--' + tipo;
        caixa.hidden = false;
    }

    function esconder() {
        caixa.hidden = true;
        caixa.innerHTML = '';
    }

    function texto(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function aplicar() {
        var codigo = cupom.value.trim();

        if (!codigo) { esconder(); return; }
        if (!kit.value) { mostrar('Escolha o kit antes de aplicar o cupom.', 'aviso'); return; }

        mostrar('Conferindo o cupom…', 'aviso');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ kit_id: kit.value, cupom: codigo })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            var d = res.d || {};
            if (!res.ok || !d.ok) {
                mostrar(texto(d.mensagem || 'Não foi possível conferir o cupom.'), 'erro');
                return;
            }
            if (d.gratuita) {
                mostrar(texto(d.mensagem) + '<strong>Total: R$ 0,00 — sem pagamento.</strong>', 'ok');
            } else {
                mostrar(texto(d.mensagem) + '<strong>' + texto(d.bruto) + ' − ' + texto(d.desconto) + ' = ' + texto(d.liquido) + '</strong>', 'ok');
            }
        })
        .catch(function () {
            mostrar('Não deu para conferir o cupom agora. Você pode enviar mesmo assim — o servidor confere de novo.', 'aviso');
        });
    }

    botao.addEventListener('click', aplicar);

    // Trocar o kit muda a conta: reaplica se já tem código.
    kit.addEventListener('change', function () { if (cupom.value.trim()) { aplicar(); } else { esconder(); } });

    // Enter no campo aplica, em vez de enviar o formulário inteiro.
    cupom.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); aplicar(); } });
    cupom.addEventListener('input', function () { cupom.value = cupom.value.toUpperCase(); });

    // A equipe também sobe em caixa alta — o servidor normaliza de novo, isto
    // é só para a pessoa ver na tela o que vai ser gravado.
    var equipe = document.getElementById('campoEquipe');
    if (equipe) { equipe.addEventListener('input', function () { equipe.value = equipe.value.toUpperCase(); }); }

    // Voltou do servidor com o cupom preenchido (old()): mostra a conta de novo.
    if (cupom.value.trim() && kit.value) { aplicar(); }
})();
</script>
@endpush
