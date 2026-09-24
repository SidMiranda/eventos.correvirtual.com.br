@extends('layouts.admin')

@section('titulo', 'Nova categoria etária')
@section('icone', 'percent')
@section('subtitulo', $event->title)

@section('conteudo')
    @include('admin._abas-do-evento')

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados da categoria</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.eventos.categorias.store', $event->id) }}">
                        @csrf
                        @include('admin.age_categories._form')
                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar categoria</button>
                        <a class="btn btn-link" href="{{ route('admin.eventos.categorias.index', $event->id) }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
