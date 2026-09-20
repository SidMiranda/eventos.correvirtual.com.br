@extends('layouts.admin')

@section('titulo', 'Fotos')
@section('icone', 'image')
@section('subtitulo', 'A galeria da home: as fotos que aparecem logo depois dos próximos eventos')

@section('acoes')
    <a class="btn btn-primary" href="{{ route('admin.fotos.create') }}">
        <i class="mr-1" data-feather="plus"></i> Adicionar fotos
    </a>
@endsection

@section('conteudo')

    <div class="card mb-4">
        <div class="card-body">
            @if ($photos->isEmpty())
                <div class="p-5 text-center text-muted">
                    <div class="mb-3"><i data-feather="image" style="width:42px;height:42px;"></i></div>
                    <p class="mb-1">Nenhuma foto ainda.</p>
                    <p class="small mb-3">
                        Enquanto não houver foto, a galeria não aparece no site. Foto de prova é o que
                        convence quem chegou agora — largada, medalha, o pastel depois.
                    </p>
                    <a class="btn btn-primary" href="{{ route('admin.fotos.create') }}">Adicionar as primeiras</a>
                </div>
            @else
                <p class="small text-muted mb-3">
                    A home mostra as <strong>12 primeiras</strong> fotos ativas, nesta ordem (menor número primeiro).
                    No celular aparecem as 6 primeiras.
                </p>

                <div class="row">
                    @foreach ($photos as $photo)
                        <div class="col-6 col-md-4 col-xl-2 mb-4">
                            <div class="card h-100 {{ $photo->active ? '' : 'foto-painel--inativa' }}">
                                <img src="{{ \App\Support\Arquivos::fotoDaGaleria($photo) }}"
                                     alt="{{ $photo->caption }}" class="foto-painel card-img-top"
                                     loading="lazy">
                                <div class="card-body p-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="badge badge-light border" title="Ordem">#{{ $photo->position }}</span>
                                        @if ($photo->active)
                                            <span class="badge badge-success-soft text-success">No site</span>
                                        @else
                                            <span class="badge badge-secondary-soft text-secondary">Fora do site</span>
                                        @endif
                                    </div>
                                    @if ($photo->caption)
                                        <div class="small text-muted mt-1 text-truncate" title="{{ $photo->caption }}">{{ $photo->caption }}</div>
                                    @endif
                                    @if ($photo->link_url)
                                        <div class="small mt-1 text-truncate">
                                            <a href="{{ $photo->link_url }}" target="_blank" rel="noopener">
                                                <i data-feather="external-link" style="width:12px;height:12px;"></i> link
                                            </a>
                                        </div>
                                    @endif
                                </div>
                                <div class="card-footer p-1 text-right text-nowrap bg-white">
                                    <a class="btn btn-datatable btn-icon btn-transparent-dark"
                                       href="{{ route('admin.fotos.edit', $photo->id) }}" title="Editar">
                                        <i data-feather="edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.fotos.destroy', $photo->id) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Apagar esta foto?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-datatable btn-icon btn-transparent-dark" title="Apagar">
                                            <i data-feather="trash-2"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

@endsection
