@extends('layouts.app')

@section('title', 'Editar inscrição - Corre Virtual')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/top-bar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/minha-conta.css') }}?v={{ filemtime(public_path('css/minha-conta.css')) }}">
@endpush

@php
    $event = $inscricao->event;
    $pode = $alteracao->permitida();
    $prazo = $alteracao->prazo();
@endphp

@section('content')
    <main class="conta">
        <a class="conta__voltar" href="{{ route('conta.inscricoes') }}">← Minhas inscrições</a>

        {{-- Miniatura e o essencial: o atleta confere que é a inscrição certa. --}}
        <div class="insc insc--cabecalho">
            <span class="insc__thumb" style="background: {{ $event->degrade() }};" aria-hidden="true">
                @if ($event->banner_url)
                    <img src="{{ \App\Support\Arquivos::cardDoEvento($event) }}" alt="" onerror="this.remove();">
                @endif
            </span>
            <div class="insc__corpo">
                <h1 class="insc__titulo">{{ $event->title }}</h1>
                <p class="insc__meta">{{ $event->event_date?->format('d/m/Y \à\s H:i') }}</p>
                <p class="insc__meta">{{ $inscricao->modality->name ?? '—' }} · {{ $inscricao->kit->name ?? '—' }}</p>
            </div>
        </div>

        <x-app.response-message />

        <form method="POST" action="{{ route('conta.inscricao', $inscricao->id) }}" class="conta__form" novalidate>
            @csrf
            @method('PUT')

            @if ($pode)
                @if ($prazo)
                    <p class="conta__aviso">Você pode alterar até <strong>{{ $prazo->format('d/m/Y \à\s H:i') }}</strong>.</p>
                @endif
            @else
                <p class="conta__aviso">{{ $alteracao->motivo() }} Não dá mais para alterar.</p>
            @endif

            @if ($alteracao->temCamiseta())
                <div class="campo">
                    <label for="camiseta">Tamanho da camiseta</label>
                    <select id="camiseta" name="camiseta" required @disabled(! $pode)>
                        <option value="">Escolha o tamanho</option>
                        @foreach ($alteracao->tamanhos() as $codigo)
                            <option value="{{ $codigo }}" @selected(old('camiseta', $inscricao->shirt_size) === $codigo)>
                                {{ \App\Models\Subscription::rotuloDoTamanho($codigo) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="campo__ajuda">Medidas aproximadas (largura x comprimento).</p>
                </div>
            @endif

            <div class="campo">
                <label for="equipe">Equipe <span class="opcional">(opcional)</span></label>
                <input id="equipe" name="equipe" type="text" maxlength="50" autocapitalize="characters"
                       placeholder="Nome da sua equipe" value="{{ old('equipe', $inscricao->team_name) }}" @disabled(! $pode)>
                <p class="campo__ajuda">Só letras, números e espaços. Em branco, fica sem equipe.</p>
            </div>

            @if ($pode)
                <button type="submit" class="botao botao--primario">Salvar alterações</button>
            @endif
        </form>

        <p class="conta__nota">Modalidade e kit não mudam por aqui — fale com a organização do evento.</p>
    </main>
@endsection
