@extends('layouts.admin')

@section('titulo', 'Lotes')
@section('icone', 'layers')
@section('subtitulo', 'As janelas de preço de todos os seus eventos')

@section('acoes')
    @include('admin._seletor-de-evento', [
        'tipo' => 'lotes',
        'tipoLabel' => 'lotes',
        'botaoLabel' => 'Novo lote',
    ])
@endsection

@section('conteudo')

    @if ($errors->has('evento'))
        <div class="alert alert-danger">{{ $errors->first('evento') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($lots->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="layers" style="width:42px;height:42px;"></i></div>
                    <p class="mb-0">Nenhum lote cadastrado ainda em nenhum evento.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 860px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Lote</th>
                                <th>Evento</th>
                                <th>Período</th>
                                <th class="text-center">Inscrições</th>
                                <th class="text-center">Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lots as $lot)
                                <tr>
                                    <td class="font-weight-500">{{ $lot->name }}</td>
                                    <td>
                                        <a href="{{ route('admin.eventos.lotes.index', $lot->event_id) }}">{{ $lot->event->title }}</a>
                                        <div class="small text-muted">{{ $lot->event->event_date?->format('d/m/Y') }}</div>
                                    </td>
                                    <td class="small">{{ $lot->periodoPorExtenso() }}</td>
                                    <td class="text-center">
                                        {{ $lot->inscricoesContadas() }}{{ $lot->max_subscriptions ? ' / ' . $lot->max_subscriptions : '' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $lot->corDaSituacao() }}-soft text-{{ $lot->corDaSituacao() }}">{{ $lot->situacao() }}</span>
                                    </td>
                                    <td class="text-right">
                                        @if ($lot->event->jaAconteceu())
                                            <span class="small text-muted" title="Evento já realizado"><i data-feather="lock" style="width:16px;height:16px;"></i></span>
                                        @else
                                            <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                               href="{{ route('admin.eventos.lotes.edit', [$lot->event_id, $lot->id]) }}" title="Editar">
                                                <i data-feather="edit"></i>
                                            </a>
                                            <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                               href="{{ route('admin.eventos.precos.edit', $lot->event_id) }}" title="Grade de preços">
                                                <i data-feather="dollar-sign"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($lots->hasPages())
            <div class="card-footer">{{ $lots->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>

@endsection
