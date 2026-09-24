@extends('layouts.admin')

@section('titulo', 'Categorias etárias')
@section('icone', 'percent')
@section('subtitulo', $event->title)

@section('acoes')
    @unless ($event->jaAconteceu())
        <a class="btn btn-primary" href="{{ route('admin.eventos.categorias.create', $event->id) }}">
            <i class="mr-1" data-feather="plus"></i> Nova categoria
        </a>
    @endunless
@endsection

@section('conteudo')

    @include('admin._abas-do-evento')

    @if ($errors->has('categoria'))
        <div class="alert alert-danger">{{ $errors->first('categoria') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($categories->isEmpty())
                <div class="p-5 text-center text-muted">
                    <p class="mb-2">Este evento não tem categorias etárias.</p>
                    <p class="mb-3 small">Categoria etária é <strong>desconto por idade</strong>: criança e idoso recebem o mesmo kit e pagam menos. Sem categoria, todo mundo paga o preço da grade.</p>
                    @unless ($event->jaAconteceu())
                        <a class="btn btn-primary" href="{{ route('admin.eventos.categorias.create', $event->id) }}">Criar a primeira</a>
                    @endunless
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 720px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Categoria</th>
                                <th>Faixa</th>
                                <th class="text-right">Desconto</th>
                                <th class="text-center">Usada em</th>
                                <th class="text-center">Situação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <td class="font-weight-500">{{ $category->name }}</td>
                                    <td>{{ $category->faixaPorExtenso() }}</td>
                                    <td class="text-right font-weight-500">{{ $category->descontoFormatado() }}</td>
                                    <td class="text-center">{{ $category->subscriptions()->count() }} inscrição(ões)</td>
                                    <td class="text-center">
                                        @if ($category->active)
                                            <span class="badge badge-success-soft text-success">Ativa</span>
                                        @else
                                            <span class="badge badge-secondary-soft text-secondary">Inativa</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @unless ($event->jaAconteceu())
                                        <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                           href="{{ route('admin.eventos.categorias.edit', [$event->id, $category->id]) }}" title="Editar">
                                            <i data-feather="edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.eventos.categorias.destroy', [$event->id, $category->id]) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Apagar a categoria &quot;{{ $category->name }}&quot;?');">
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
        A idade é contada pelo critério definido em
        <a href="{{ route('admin.eventos.edit', $event->id) }}">Dados do evento</a>
        ({{ $event->age_criteria === \App\Models\Event::CRITERIO_DATA_EXATA ? 'data exata' : 'ano-calendário' }}),
        a partir da data de nascimento do cadastro do atleta.
    </p>

@endsection
