@extends('layouts.admin')

@section('titulo', 'Editar categoria etária')
@section('icone', 'percent')
@section('subtitulo', $event->title . ' — ' . $category->name)

@section('conteudo')
    @include('admin._abas-do-evento')

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados da categoria</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.eventos.categorias.update', [$event->id, $category->id]) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.age_categories._form', ['category' => $category])
                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        <a class="btn btn-link" href="{{ route('admin.eventos.categorias.index', $event->id) }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
