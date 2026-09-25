@extends('layouts.app')

@section('title', 'Minha conta - Corre Virtual')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/top-bar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/minha-conta.css') }}?v={{ filemtime(public_path('css/minha-conta.css')) }}">
@endpush

@section('content')
    <main class="conta">
        <h1 class="conta__titulo">Minha conta</h1>
        @include('conta._abas')

        <x-app.response-message />

        @if ($inscricoes->vazia())
            <div class="conta__vazio">
                <p>Você ainda não tem nenhuma inscrição.</p>
                <a href="{{ url('/') }}" class="botao botao--primario">Ver os eventos</a>
            </div>
        @else
            <section class="conta__secao" aria-labelledby="titulo-proximas">
                <h2 class="conta__subtitulo" id="titulo-proximas">Próximas <span>{{ $inscricoes->proximas->count() }}</span></h2>
                @forelse ($inscricoes->proximas as $inscricao)
                    @include('conta._linha-inscricao', ['inscricao' => $inscricao])
                @empty
                    <p class="conta__nada">Nenhuma prova pela frente. <a href="{{ url('/') }}">Ver os eventos</a></p>
                @endforelse
            </section>

            @if ($inscricoes->realizadas->isNotEmpty())
                <section class="conta__secao" aria-labelledby="titulo-realizadas">
                    <h2 class="conta__subtitulo" id="titulo-realizadas">Realizadas <span>{{ $inscricoes->realizadas->count() }}</span></h2>
                    @foreach ($inscricoes->realizadas as $inscricao)
                        @include('conta._linha-inscricao', ['inscricao' => $inscricao])
                    @endforeach
                </section>
            @endif
        @endif
    </main>

    @include('conta._aviso-da-inscricao')
@endsection
