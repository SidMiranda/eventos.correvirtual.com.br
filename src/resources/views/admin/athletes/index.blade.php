@extends('layouts.admin')

@section('titulo', 'Atletas')
@section('icone', 'user')
@section('subtitulo', 'Quem já se inscreveu em algum evento seu')

@section('conteudo')

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.atletas.index') }}" class="form-inline">
                <label class="sr-only" for="busca">Buscar atleta</label>
                <input class="form-control mr-2 mb-2 mb-sm-0" id="busca" name="busca" type="search"
                       placeholder="Nome, e-mail ou CPF" style="min-width: 260px;"
                       value="{{ $busca }}">

                <button class="btn btn-primary mb-2 mb-sm-0" type="submit">
                    <i data-feather="search" style="width:16px;height:16px;"></i>
                    <span class="ml-1">Buscar</span>
                </button>

                @if ($busca !== '')
                    <a class="btn btn-link text-muted mb-2 mb-sm-0" href="{{ route('admin.atletas.index') }}">Limpar</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body p-0">
            @if ($atletas->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="user" style="width:42px;height:42px;"></i></div>
                    @if ($busca !== '')
                        <p class="mb-1">Nenhum atleta encontrado com essa busca.</p>
                        <a class="btn btn-link" href="{{ route('admin.atletas.index') }}">Ver todos</a>
                    @else
                        <p class="mb-1">Nenhum atleta ainda.</p>
                        <p class="small mb-0">A lista mostra quem já se inscreveu em algum evento seu.</p>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="min-width: 860px;">
                        <thead class="thead-light">
                            <tr>
                                <th>Atleta</th>
                                <th>Contato</th>
                                <th class="text-center">Inscrições</th>
                                <th>Última inscrição</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($atletas as $atleta)
                                <tr>
                                    <td>
                                        <a class="font-weight-500" href="{{ route('admin.atletas.show', $atleta->id) }}">
                                            {{ $atleta->name }}
                                        </a>
                                        @if ($atleta->is_pcd)
                                            <span class="badge badge-blue-soft text-blue ml-1">PCD</span>
                                        @endif
                                        @if ($atleta->cpf)
                                            <div class="small text-muted">CPF {{ $atleta->cpf }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="small">{{ $atleta->email }}</div>
                                        @if ($atleta->phone)
                                            <div class="small text-muted">{{ $atleta->phone }}</div>
                                        @endif
                                        @if ($atleta->city)
                                            <div class="small text-muted">{{ $atleta->city->nomeCompleto() }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $atleta->inscricoes_count }}</td>
                                    <td class="small text-muted">
                                        {{ $atleta->ultima_inscricao ? \Carbon\Carbon::parse($atleta->ultima_inscricao)->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                           href="{{ route('admin.atletas.show', $atleta->id) }}" title="Ver ficha">
                                            <i data-feather="eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @if ($atletas->hasPages())
            <div class="card-footer">{{ $atletas->links('pagination::bootstrap-4') }}</div>
        @endif
    </div>

@endsection
