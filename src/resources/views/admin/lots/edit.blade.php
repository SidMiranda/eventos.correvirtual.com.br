@extends('layouts.admin')

@section('titulo', 'Editar lote')
@section('icone', 'layers')
@section('subtitulo', $event->title . ' — ' . $lot->name)

@section('conteudo')
    @include('admin._abas-do-evento')

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados do lote</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.eventos.lotes.update', [$event->id, $lot->id]) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.lots._form', ['lot' => $lot])
                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        <a class="btn btn-link" href="{{ route('admin.eventos.lotes.index', $event->id) }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
