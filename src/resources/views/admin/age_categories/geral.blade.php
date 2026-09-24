@extends('layouts.admin')

@section('titulo', 'Categorias etárias')
@section('icone', 'percent')
@section('subtitulo', 'Descontos por idade, em todos os seus eventos')

@section('acoes')
    @include('admin._seletor-de-evento', [
        'tipo' => 'categorias',
        'tipoLabel' => 'categorias',
        'botaoLabel' => 'Nova categoria',
    ])
@endsection

@section('conteudo')

    @if ($errors->has('evento'))
        <div class="alert alert-danger">{{ $errors->first('evento') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($categories->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="percent" style="width:42px;height:42px;"></i></div>
                    <p class="mb-0">Nenhuma categoria etária cadastrada ainda em nenhum evento.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 820px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Categoria</th>
                                <th>Evento</th>
                                <th>Faixa</th>
                                <th class="text-right">Desconto</th>
                                <th class="text-center">Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="font-weight-500">{{ $category->name }}</td>
                                    <td>
                                        <a href="{{ route('admin.eventos.categorias.index', $category->event_id) }}">{{ $category->event->title }}</a>
                                        <div class="small text-muted">{{ $category->event->event_date?->format('d/m/Y') }}</div>
                                    </td>
                                    <td>{{ $category->faixaPorExtenso() }}</td>
                                    <td class="text-right font-weight-500">{{ $category->descontoFormatado() }}</td>
                                    <td class="text-center">
                                        @if ($category->active)
                                            <span class="badge badge-success-soft text-success">Ativa</span>
                                        @else
                                            <span class="badge badge-secondary-soft text-secondary">Inativa</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($category->event->jaAconteceu())
                                            <span class="small text-muted" title="Evento já realizado"><i data-feather="lock" style="width:16px;height:16px;"></i></span>
                                        @else
                                            <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                               href="{{ route('admin.eventos.categorias.edit', [$category->event_id, $category->id]) }}" title="Editar">
                                                <i data-feather="edit"></i>
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

        @if ($categories->hasPages())
            <div class="card-footer">{{ $categories->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>

@endsection
