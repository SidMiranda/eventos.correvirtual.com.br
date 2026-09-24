@extends('layouts.admin')

@section('titulo', 'Lotes')
@section('icone', 'layers')
@section('subtitulo', $event->title)

@section('acoes')
    @unless ($event->jaAconteceu())
        <a class="btn btn-primary" href="{{ route('admin.eventos.lotes.create', $event->id) }}">
            <i class="mr-1" data-feather="plus"></i> Novo lote
        </a>
    @endunless
@endsection

@section('conteudo')

    @include('admin._abas-do-evento')

    @if ($errors->has('lote'))
        <div class="alert alert-danger">{{ $errors->first('lote') }}</div>
    @endif

    @if ($event->jaAconteceu())
        <div class="alert alert-icon" role="alert" style="background:#f1f4f8; border:1px solid #dbe3ec;">
            <div class="alert-icon-aside"><i data-feather="lock"></i></div>
            <div class="alert-icon-content">
                Este evento já aconteceu em <strong>{{ $event->event_date->format('d/m/Y') }}</strong> — os lotes ficam só para consulta.
            </div>
        </div>
    @elseif ($lots->isNotEmpty() && ! $event->loteVigente())
        <div class="alert alert-warning">
            <strong>Nenhum lote está vigente agora</strong> — as inscrições estão fechadas até um lote entrar na janela.
            @if ($proximo = $event->proximoLote())
                O próximo, "{{ $proximo->name }}", abre em {{ $proximo->starts_at->format('d/m/Y \à\s H:i') }}.
            @endif
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($lots->isEmpty())
                <div class="p-5 text-center text-muted">
                    <p class="mb-2">Este evento ainda não tem lotes.</p>
                    <p class="mb-3 small">O lote é a janela em que um preço vale. <strong>Sem lote vigente, o evento não vende</strong> — o preço de cada modalidade e kit é definido por lote, na grade de preços.</p>
                    @unless ($event->jaAconteceu())
                        <a class="btn btn-primary" href="{{ route('admin.eventos.lotes.create', $event->id) }}">Criar o primeiro</a>
                    @endunless
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 820px;">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 60px;">Ordem</th>
                                <th>Lote</th>
                                <th>Período</th>
                                <th class="text-center">Limite</th>
                                <th class="text-center">Inscrições</th>
                                <th class="text-center">Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <td class="text-muted">{{ $lot->position }}</td>
                                    <td class="font-weight-500">{{ $lot->name }}</td>
                                    <td class="small">{{ $lot->periodoPorExtenso() }}</td>
                                    <td class="text-center">{{ $lot->max_subscriptions ?? 'sem limite' }}</td>
                                    <td class="text-center">{{ $lot->inscricoesContadas() }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $lot->corDaSituacao() }}-soft text-{{ $lot->corDaSituacao() }}">{{ $lot->situacao() }}</span>
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @unless ($event->jaAconteceu())
                                        <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                           href="{{ route('admin.eventos.lotes.edit', [$event->id, $lot->id]) }}" title="Editar">
                                            <i data-feather="edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.eventos.lotes.destroy', [$event->id, $lot->id]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Apagar o lote &quot;{{ $lot->name }}&quot;? Os preços dessa coluna somem junto.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-datatable btn-icon btn-transparent-dark" title="Apagar">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <p class="small text-muted">
        O lote vigente é escolhido pelo sistema na hora da inscrição: o de menor ordem que esteja na janela e ainda tenha vaga.
        Ninguém precisa "ativar" o próximo — cadastre as janelas e a virada é automática.
        Os valores de cada lote ficam na aba <a href="{{ route('admin.eventos.precos.edit', $event->id) }}">Preços</a>.
    </p>

@endsection
