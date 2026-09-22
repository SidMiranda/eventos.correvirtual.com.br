@extends('layouts.admin')

@section('titulo', 'Inscrições')
@section('icone', 'clipboard')
@section('subtitulo', 'Quem se inscreveu nos seus eventos')

@section('acoes')
    {{-- O relatório é de uma prova: sem evento escolhido, o botão explica em
         vez de gerar um papel que mistura eventos diferentes. --}}
    @if ($filtro->evento)
        <a class="btn btn-primary" target="_blank" rel="noopener"
           href="{{ route('admin.inscricoes.pdf', $filtro->paraUrl()) }}">
            <i class="mr-1" data-feather="file-text"></i> Relatório em PDF
        </a>
    @else
        <span class="btn btn-primary disabled" style="cursor: not-allowed;"
              title="Escolha um evento no filtro: o relatório é a lista de inscritos de uma prova.">
            <i class="mr-1" data-feather="file-text"></i> Relatório em PDF
        </span>
    @endif
@endsection

@section('conteudo')

    {{-- Os números do recorte atual: mudam junto com o filtro, então o que se
         lê aqui é sempre o que está na tabela abaixo. --}}
    <div class="row">
        @php
            $cartoes = [
                ['Inscrições', $totais['inscricoes'], 'cv-blue', 'users'],
                ['Pagas', $totais['pagas'], 'success', 'check-circle'],
                ['Aguardando', $totais['pendentes'], 'warning', 'clock'],
                ['Canceladas', $totais['canceladas'], 'danger', 'x-circle'],
            ];
        @endphp

        @foreach ($cartoes as [$rotulo, $valor, $cor, $icone])
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small font-weight-bold text-{{ $cor }} mb-1">{{ $rotulo }}</div>
                            <div class="h3 mb-0">{{ $valor }}</div>
                        </div>
                        <i data-feather="{{ $icone }}" style="width:30px;height:30px;opacity:.3;"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small font-weight-bold text-success mb-1">Arrecadado</div>
                    <div class="h4 mb-0">R$ {{ number_format($totais['arrecadado'], 2, ',', '.') }}</div>
                    <div class="small text-muted mt-1">Soma do que foi cobrado nas inscrições pagas.</div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small font-weight-bold text-cv-blue mb-1">Descontos concedidos</div>
                    <div class="h4 mb-0">R$ {{ number_format($totais['descontos'], 2, ',', '.') }}</div>
                    <div class="small text-muted mt-1">Quanto os cupons abateram nas inscrições pagas.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.inscricoes.index') }}" class="form-inline">
                <label class="small text-muted mr-2 mb-0" for="evento">Evento</label>
                <select class="form-control mr-2 mb-2 mb-sm-0" id="evento" name="evento" style="min-width: 230px;">
                    <option value="">Todos</option>
                    @foreach ($eventos as $evento)
                        <option value="{{ $evento->id }}" @selected($filtro->evento === $evento->id)>
                            {{ $evento->title }} ({{ $evento->event_date?->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>

                <label class="small text-muted mr-2 mb-0" for="situacao">Situação</label>
                <select class="form-control mr-2 mb-2 mb-sm-0" id="situacao" name="situacao">
                    <option value="">Todas</option>
                    <option value="pagas" @selected($filtro->situacao === 'pagas')>Pagas</option>
                    <option value="pendentes" @selected($filtro->situacao === 'pendentes')>Aguardando pagamento</option>
                    <option value="canceladas" @selected($filtro->situacao === 'canceladas')>Canceladas</option>
                </select>

                <label class="sr-only" for="busca">Buscar atleta</label>
                <input class="form-control mr-2 mb-2 mb-sm-0" id="busca" name="busca" type="search"
                       placeholder="Nome, e-mail ou CPF" style="min-width: 190px;"
                       value="{{ $filtro->busca }}">

                <div class="custom-control custom-checkbox mr-3 mb-2 mb-sm-0">
                    <input type="checkbox" class="custom-control-input" id="desconto" name="desconto" value="1"
                           @checked($filtro->comDesconto)>
                    <label class="custom-control-label small" for="desconto">Só com desconto</label>
                </div>

                <button class="btn btn-primary mb-2 mb-sm-0" type="submit">
                    <i data-feather="search" style="width:16px;height:16px;"></i>
                    <span class="ml-1">Filtrar</span>
                </button>

                @if ($filtro->ativo())
                    <a class="btn btn-link text-muted mb-2 mb-sm-0" href="{{ route('admin.inscricoes.index') }}">Limpar</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($inscricoes->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="clipboard" style="width:42px;height:42px;"></i></div>
                    @if ($filtro->ativo())
                        <p class="mb-1">Nenhuma inscrição com esse filtro.</p>
                        <a class="btn btn-link" href="{{ route('admin.inscricoes.index') }}">Ver todas</a>
                    @else
                        <p class="mb-1">Nenhuma inscrição ainda.</p>
                        <p class="small mb-0">Quando alguém se inscrever num evento seu, aparece aqui.</p>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 1020px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Atleta</th>
                                <th>Evento</th>
                                <th>Modalidade / kit</th>
                                <th class="text-right">Valor</th>
                                <th class="text-center">Situação</th>
                                <th>Inscrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inscricoes as $inscricao)
                                <tr>
                                    <td>
                                        <a class="font-weight-500" href="{{ route('admin.atletas.show', $inscricao->user_id) }}">
                                            {{ $inscricao->user->name ?? 'Atleta removido' }}
                                        </a>
                                        <div class="small text-muted">{{ $inscricao->user->email ?? '—' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $inscricao->event->title }}</div>
                                        <div class="small text-muted">{{ $inscricao->event->event_date?->format('d/m/Y') }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $inscricao->modality->name ?? '—' }}</div>
                                        <div class="small text-muted">{{ $inscricao->kit->name ?? '—' }}</div>
                                        {{-- A equipe entra aqui e não numa coluna nova: a tabela já
                                             tem seis e nem toda inscrição tem equipe. --}}
                                        @if ($inscricao->team_name)
                                            <div class="small"><i data-feather="users" style="width:12px;height:12px;"></i> {{ $inscricao->team_name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="font-weight-500">R$ {{ number_format($inscricao->price, 2, ',', '.') }}</div>
                                        @if ($inscricao->temDesconto())
                                            <div class="small text-success">
                                                {{ $inscricao->coupon?->code ?? 'cupom' }}:
                                                −R$ {{ number_format($inscricao->discount_amount, 2, ',', '.') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $inscricao->corDaSituacao() }}-soft text-{{ $inscricao->corDaSituacao() }}">
                                            {{ $inscricao->situacao() }}
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        {{ $inscricao->created_at?->format('d/m/Y') }}
                                        @if ($inscricao->cancelada() && $inscricao->cancelled_at)
                                            <div>cancelada em {{ $inscricao->cancelled_at->format('d/m/Y') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($inscricoes->hasPages())
            <div class="card-footer">{{ $inscricoes->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>

@endsection
