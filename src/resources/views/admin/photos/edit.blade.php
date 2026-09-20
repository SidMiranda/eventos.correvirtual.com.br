@extends('layouts.admin')

@section('titulo', 'Editar foto')
@section('icone', 'image')
@section('subtitulo', $photo->caption ?: 'Foto #' . $photo->id)

@section('conteudo')
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados da foto</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.fotos.update', $photo->id) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.photos._form', ['photo' => $photo])
                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        <a class="btn btn-link" href="{{ route('admin.fotos.index') }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
