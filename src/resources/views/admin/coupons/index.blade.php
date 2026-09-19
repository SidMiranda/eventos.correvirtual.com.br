@extends('layouts.admin')

@section('titulo', 'Cupons')
@section('icone', 'tag')
@section('subtitulo', 'Descontos dos seus eventos: quanto, quantos e até quando')

@php
    $temFiltro = $eventoFiltro || $busca !== '';

    // O que o modal precisa saber de cada cupom da página para abrir preenchido.
    // Vai como JSON no rodapé — é mais legível que espalhar uma dúzia de
    // data-attributes por linha da tabela.
    $mapa = collect($cupons->items())->mapWithKeys(fn ($cupom) => [$cupom->id => [
        'event_id' => $cupom->event_id,
        'evento' => $cupom->event->title . ' (' . $cupom->event->event_date?->format('d/m/Y') . ')',
        'code' => $cupom->code,
        'description' => $cupom->description,
        'discount_type' => $cupom->discount_type,
        'discount_value' => (float) $cupom->discount_value,
        'total_quantity' => $cupom->total_quantity,
        'used_quantity' => $cupom->used_quantity,
        'expires_at' => $cupom->expires_at?->toDateString(),
        'active' => (bool) $cupom->active,
        'travado' => $cupom->foiUsado(),
    ]]);
@endphp

@section('acoes')
    @if ($eventosParaCupom->isEmpty())
        {{-- Sem evento futuro não há o que descontar. Mesma conversa do seletor
             de modalidades e kits: em vez de um botão que abre um formulário
             impossível de enviar, o caminho de saída. --}}
        <div class="alert alert-warning mb-0 py-2 px-3 small" role="alert">
            Nenhum evento futuro para criar cupom.
            <a href="{{ route('admin.eventos.create') }}">Criar um evento</a>.
        </div>
    @else
        <button class="btn btn-primary" type="button" onclick="abrirCupom(null)">
            <i class="mr-1" data-feather="plus"></i> Criar cupom
        </button>
    @endif
@endsection

@section('conteudo')

    @if ($cupons->isNotEmpty() || $temFiltro)
        <div class="card mb-4">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.cupons.index') }}" class="form-inline">
                    <label class="small text-muted mr-2 mb-0" for="evento">Evento</label>
                    <select class="form-control mr-2 mb-2 mb-sm-0" id="evento" name="evento" style="min-width: 240px;">
                        <option value="">Todos</option>
                        @foreach ($eventosDoFiltro as $evento)
                            <option value="{{ $evento->id }}" @selected($eventoFiltro === $evento->id)>
                                {{ $evento->title }} ({{ $evento->event_date?->format('d/m/Y') }})
                            </option>
                        @endforeach
                    </select>

                    <label class="sr-only" for="busca">Buscar por código</label>
                    <input class="form-control mr-2 mb-2 mb-sm-0" id="busca" name="busca" type="search"
                           placeholder="Buscar por código" style="min-width: 200px;"
                           value="{{ $busca }}">

                    <button class="btn btn-primary mb-2 mb-sm-0" type="submit">
                        <i data-feather="search" style="width:16px;height:16px;"></i>
                        <span class="ml-1">Filtrar</span>
                    </button>

                    @if ($temFiltro)
                        <a class="btn btn-link text-muted mb-2 mb-sm-0" href="{{ route('admin.cupons.index') }}">Limpar</a>
                    @endif
                </form>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($cupons->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="tag" style="width:42px;height:42px;"></i></div>

                    @if ($temFiltro)
                        <p class="mb-1">Nenhum cupom encontrado com esse filtro.</p>
                        <a class="btn btn-link" href="{{ route('admin.cupons.index') }}">Ver todos os cupons</a>
                    @else
                        <p class="mb-1">Nenhum cupom criado ainda.</p>
                        <p class="small mb-3">
                            Um cupom dá desconto num evento seu sem mexer no preço do kit —
                            útil para parceria com assessoria, cortesia de patrocinador ou campanha de lançamento.
                        </p>
                        @if ($eventosParaCupom->isNotEmpty())
                            <button class="btn btn-primary" type="button" onclick="abrirCupom(null)">
                                Criar o primeiro cupom
                            </button>
                        @endif
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 980px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Código</th>
                                <th>Evento</th>
                                <th class="text-right">Desconto</th>
                                <th>Expira em</th>
                                <th class="text-center">Geradas</th>
                                <th class="text-center">Utilizadas</th>
                                <th class="text-center">Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cupons as $cupom)
                                <tr>
                                    <td>
                                        <div class="font-weight-500 text-monospace">{{ $cupom->code }}</div>
                                        @if ($cupom->description)
                                            <div class="small text-muted">{{ Str::limit($cupom->description, 60) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $cupom->event->title }}</div>
                                        <div class="small text-muted">{{ $cupom->event->event_date?->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="text-right font-weight-500">{{ $cupom->descontoFormatado() }}</td>
                                    <td>
                                        {{ $cupom->expires_at?->format('d/m/Y') }}
                                        @if ($cupom->vencido())
                                            <div class="small text-danger">vencido</div>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $cupom->total_quantity }}</td>
                                    <td class="text-center">
                                        {{ $cupom->used_quantity }}
                                        @if ($cupom->foiUsado() && ! $cupom->esgotado())
                                            <div class="small text-muted">restam {{ $cupom->restantes() }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- O toggle é um form de verdade: o painel não é SPA, e
                                             uma caixinha que só muda de cor sem gravar nada seria
                                             pior que não ter toggle. --}}
                                        <form method="POST" action="{{ route('admin.cupons.status', $cupom->id) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <div class="custom-control custom-switch d-inline-block text-left">
                                                <input class="custom-control-input" type="checkbox"
                                                       id="status-{{ $cupom->id }}"
                                                       onchange="this.form.submit()"
                                                       @checked($cupom->active && ! $cupom->encerrado())
                                                       @disabled($cupom->encerrado())>
                                                <label class="custom-control-label" for="status-{{ $cupom->id }}">
                                                    <span class="badge badge-{{ $cupom->corDaSituacao() }}-soft text-{{ $cupom->corDaSituacao() }}">
                                                        {{ $cupom->situacao() }}
                                                    </span>
                                                </label>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        <button class="btn btn-datatable btn-icon btn-transparent-dark" type="button"
                                                title="Editar" onclick="abrirCupom({{ $cupom->id }})">
                                            <i data-feather="edit"></i>
                                        </button>

                                        @if ($cupom->foiUsado())
                                            {{-- Sem <button disabled>: navegador nenhum mostra o
                                                 title de um botão desabilitado, e o motivo é
                                                 justamente o que a pessoa precisa ler aqui. --}}
                                            <span class="btn btn-datatable btn-icon text-muted" style="cursor: not-allowed;"
                                                  title="Já foi usado {{ $cupom->used_quantity }}x — não pode ser apagado. Desative para tirar de circulação.">
                                                <i data-feather="trash-2"></i>
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('admin.cupons.destroy', $cupom->id) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Apagar o cupom &quot;{{ $cupom->code }}&quot;?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-datatable btn-icon btn-transparent-dark" title="Apagar">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($cupons->hasPages())
            <div class="card-footer">{{ $cupons->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>

    @include('admin.coupons._modal', ['eventosParaCupom' => $eventosParaCupom])

@endsection

@push('scripts')
<script>
(function () {
    var CUPONS = @json($mapa);
    var ROTA_STORE = @json(route('admin.cupons.store'));
    var ROTA_UPDATE = @json(route('admin.cupons.update', ['id' => '__ID__']));
    var TIPO_PERCENTUAL = @json(\App\Models\Coupon::TIPO_PERCENTUAL);
    var FORMATO_CODIGO = /^[A-Z0-9]{6,7}$/;

    var form = document.getElementById('formCupom');
    var campos = {
        metodo: document.getElementById('cupom_method'),
        id: document.getElementById('cupom_id'),
        evento: document.getElementById('event_id'),
        codigo: document.getElementById('code'),
        tipo: document.getElementById('discount_type'),
        valor: document.getElementById('discount_value'),
        quantidade: document.getElementById('total_quantity'),
        validade: document.getElementById('expires_at'),
        descricao: document.getElementById('description'),
        ativo: document.getElementById('active')
    };
    var titulo = document.getElementById('tituloModalCupom');
    var botao = document.getElementById('botaoSalvarCupom');
    var aviso = document.getElementById('avisoCupomUsado');
    var dicaCodigo = document.getElementById('dicaCodigo');
    var dicaDesconto = document.getElementById('dicaDesconto');
    var prefixo = document.getElementById('prefixoDesconto');

    /* O prefixo e o limite mudam com o tipo: "%" pede 1 a 100, "R$" pede
       qualquer valor acima de zero. Sem isso o campo aceitaria 350% em silêncio
       e só o servidor reclamaria. */
    function aplicarTipo() {
        var percentual = campos.tipo.value === TIPO_PERCENTUAL;

        prefixo.textContent = percentual ? '%' : 'R$';
        campos.valor.max = percentual ? 100 : 99999.99;
        campos.valor.min = percentual ? 1 : 0.01;

        if (dicaDesconto) {
            dicaDesconto.textContent = percentual
                ? 'De 1 a 100.'
                : 'Em reais, acima de zero.';
        }
    }

    /* Validação em tempo real do código: maiúsculas enquanto digita (é como o
       valor vai para o banco) e aviso imediato de formato. A repetição continua
       sendo conferida no servidor, onde está o banco. */
    function validarCodigo() {
        var valor = campos.codigo.value.toUpperCase();

        if (valor !== campos.codigo.value) {
            campos.codigo.value = valor;
        }

        var vazio = valor.length === 0;
        var valido = FORMATO_CODIGO.test(valor);

        campos.codigo.classList.toggle('is-invalid', !vazio && !valido);
        campos.codigo.classList.toggle('is-valid', valido);

        if (!dicaCodigo) {
            return;
        }

        if (vazio || valido) {
            dicaCodigo.textContent = '6 ou 7 caracteres, letras e números.';
            dicaCodigo.className = 'form-text text-muted';
        } else if (/[^A-Z0-9]/.test(valor)) {
            dicaCodigo.textContent = 'Use apenas letras de A a Z e números — sem espaços, acentos ou símbolos.';
            dicaCodigo.className = 'form-text text-danger';
        } else {
            dicaCodigo.textContent = 'Faltam ' + (6 - valor.length) + ' caractere(s): o código tem 6 ou 7.';
            dicaCodigo.className = 'form-text text-danger';
        }
    }

    /* Cupom já usado: código e evento congelados.

       O código vai readonly e não disabled de propósito — campo desabilitado
       não é enviado, e o servidor receberia uma edição sem código. O select do
       evento, esse sim, vai disabled: o servidor simplesmente não mexe no
       evento quando ele não vem. As duas travas são repetidas no controller. */
    function travar(travado) {
        campos.codigo.readOnly = travado;
        campos.evento.disabled = travado;
        aviso.classList.toggle('d-none', !travado);
    }

    /* Evento que já aconteceu não está no <select> (não recebe cupom novo), mas
       precisa continuar aparecendo na edição de um cupom antigo. */
    function garantirOpcaoEvento(id, rotulo) {
        if (campos.evento.querySelector('option[value="' + id + '"]')) {
            return;
        }

        var opcao = document.createElement('option');
        opcao.value = id;
        opcao.textContent = rotulo;
        campos.evento.appendChild(opcao);
    }

    /* Abre o modal. Sem id, em modo criação. */
    window.abrirCupom = function (id) {
        var cupom = id ? CUPONS[id] : null;

        form.action = cupom ? ROTA_UPDATE.replace('__ID__', id) : ROTA_STORE;
        campos.metodo.value = cupom ? 'PUT' : 'POST';
        campos.id.value = cupom ? id : '';
        titulo.textContent = cupom ? 'Editar cupom' : 'Criar cupom';
        botao.textContent = cupom ? 'Salvar alterações' : 'Criar cupom';

        if (cupom) {
            garantirOpcaoEvento(cupom.event_id, cupom.evento);
            campos.evento.value = cupom.event_id;
            campos.codigo.value = cupom.code;
            campos.tipo.value = cupom.discount_type;
            campos.valor.value = cupom.discount_value;
            campos.quantidade.value = cupom.total_quantity;
            // A quantidade não desce abaixo do que já foi usado — senão a
            // listagem mostraria "geradas 1 / utilizadas 3".
            campos.quantidade.min = Math.max(1, cupom.used_quantity);
            campos.validade.value = cupom.expires_at;
            campos.descricao.value = cupom.description || '';
            campos.ativo.checked = cupom.active;
        } else {
            campos.evento.selectedIndex = 0;
            campos.codigo.value = '';
            campos.tipo.selectedIndex = 0;
            campos.valor.value = '';
            campos.quantidade.value = '';
            campos.quantidade.min = 1;
            campos.validade.value = '';
            campos.descricao.value = '';
            campos.ativo.checked = true;
        }

        travar(cupom ? cupom.travado : false);
        aplicarTipo();
        validarCodigo();

        $('#modalCupom').modal('show');
    };

    campos.tipo.addEventListener('change', aplicarTipo);
    campos.codigo.addEventListener('input', validarCodigo);

    @if ($errors->any() && old('code') !== null)
        /* O servidor recusou o envio. O modal reabre com o que foi digitado
           (os campos já vieram com old() do Blade), na mesma trava de antes —
           reabrir em branco faria a pessoa digitar tudo de novo. */
        document.addEventListener('DOMContentLoaded', function () {
            var id = campos.id.value;
            var cupom = id ? CUPONS[id] : null;

            form.action = id ? ROTA_UPDATE.replace('__ID__', id) : ROTA_STORE;
            campos.metodo.value = id ? 'PUT' : 'POST';
            titulo.textContent = id ? 'Editar cupom' : 'Criar cupom';
            botao.textContent = id ? 'Salvar alterações' : 'Criar cupom';

            if (cupom) {
                garantirOpcaoEvento(cupom.event_id, cupom.evento);
                if (cupom.travado) {
                    campos.evento.value = cupom.event_id;
                }
            }

            travar(cupom ? cupom.travado : false);
            aplicarTipo();

            $('#modalCupom').modal('show');
        });
    @endif
})();
</script>
@endpush
