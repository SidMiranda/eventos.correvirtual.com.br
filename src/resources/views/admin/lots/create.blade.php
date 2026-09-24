@extends('layouts.admin')

@section('titulo', 'Novo lote')
@section('icone', 'layers')
@section('subtitulo', $event->title)

@section('conteudo')
    @include('admin._abas-do-evento')

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados do lote</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.eventos.lotes.store', $event->id) }}">
                        @csrf
                        @include('admin.lots._form')
                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar lote</button>
                        <a class="btn btn-link" href="{{ route('admin.eventos.lotes.index', $event->id) }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
