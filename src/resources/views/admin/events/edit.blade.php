@extends('layouts.admin')

@section('titulo', 'Editar evento')
@section('icone', 'calendar')
@section('subtitulo', $event->title)
@include('admin._banner-do-evento', ['event' => $event])

@section('conteudo')

    <div class="row justify-content-center">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">Dados do evento</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.eventos.update', $event->id) }}">
                        @csrf
                        @method('PUT')

                        @include('admin.events._form', ['event' => $event])

                        <hr class="my-4">

                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        <a class="btn btn-link" href="{{ route('admin.eventos.index') }}">Cancelar</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
