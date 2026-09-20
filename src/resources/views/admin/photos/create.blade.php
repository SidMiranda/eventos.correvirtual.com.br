@extends('layouts.admin')

@section('titulo', 'Adicionar fotos')
@section('icone', 'image')

@section('conteudo')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Fotos para a galeria da home</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.fotos.store') }}">
                        @csrf

                        <div class="form-group">
                            <label class="small mb-1" for="fotos">Arquivos</label>
                            <input class="form-control-file @error('fotos') is-invalid @enderror"
                                   id="fotos" name="fotos[]" type="file" multiple required
                                   accept="image/jpeg,image/png,image/webp">
                            @error('fotos')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">
                                    Pode escolher várias de uma vez — até <strong>{{ \App\Http\Controllers\Admin\PhotoController::MAXIMO_POR_ENVIO }} por envio</strong>,
                                    5 MB cada e <strong>16 MB no total</strong> (foto de celular costuma ter 3 a 5 MB: três ou quatro por vez).
                                    Cada foto vira um quadrado, recortado pelo centro — como no Instagram. Legenda, link e ordem
                                    você ajusta depois, foto a foto.
                                </small>
                            @enderror
                        </div>

                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Enviar fotos</button>
                        <a class="btn btn-link" href="{{ route('admin.fotos.index') }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
