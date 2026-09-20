@extends('layouts.app-v2')

@section('title', 'Eventos - Corre Virtual')

@push('styles')
    {{-- ?v={{ filemtime(...) }}: sem isso uma mudança de estilo só aparece para
         quem limpar o cache do navegador. Mesmo cuidado já tomado na Home v2. --}}
    <link rel="stylesheet" href="{{ asset('css/event-cards.css') }}?v={{ filemtime(public_path('css/event-cards.css')) }}">
@endpush

@php
    // Qual modelo de card usar. Ver config/aparencia.php — as duas versões
    // continuam mantidas, e trocar é mudar CARD_DE_EVENTO no .env.
    $cardDeEvento = config('aparencia.card_de_evento') === 'v1'
        ? 'app.event-card'
        : 'app.event-card-v2';
@endphp

@section('content')

    <x-app.banner-v2 />

    <div class="container" id="eventos">
        <h2 class="block-header-title">
            PRÓXIMOS <span> EVENTOS </span>
        </h2>

        @if($proximosEventos->isEmpty())
            <p class="eventos-vazio">
                Nenhuma prova com inscrição aberta no momento. Fique de olho — em breve tem mais.
            </p>
        @else
            <div class="cards-grid">
                @foreach($proximosEventos as $event)
                    <x-dynamic-component :component="$cardDeEvento" :event="$event" />
                @endforeach
            </div>
        @endif
    </div>

    {{-- A galeria de fotos, a 100% da largura, logo depois do que está
         aberto: foto de prova é o que convence quem chegou agora. Sem foto,
         a seção não existe. Cadastro em /admin/fotos. --}}
    @if($fotos->isNotEmpty())
        <x-app.fotos :fotos="$fotos" />
    @endif

    {{-- Patrocinadores logo depois do que está aberto: é ali que o visitante
         ainda está olhando a página, e é o que a marca patrocinadora paga
         para ver. Antes ficava no fim, depois do "sobre nós".

         Sem nenhum cadastrado, a seção não aparece — uma fileira vazia com
         título é pior que não ter a seção. Cadastro em /admin/patrocinadores. --}}
    @if($patrocinadores->isNotEmpty())
        <div class="container" id="patrocinadores">
            <h2 class="block-header-title">
                NOSSOS <span> PATROCINADORES </span>
            </h2>

            <x-app.sponsors :patrocinadores="$patrocinadores" />
        </div>
    @endif

    @if($eventosRealizados->isNotEmpty())
        {{-- Vitrine: prova entregue é o melhor argumento de quem está decidindo
             se confia no organizador. Só a arte, sem link — a prova acabou, não
             há o que fazer com ela. Ver App\Support\GaleriaDeRealizados. --}}
        <div class="container" id="eventos-realizados">
            <h2 class="block-header-title">
                EVENTOS <span> REALIZADOS </span>
            </h2>

            <div class="cards-grid cards-grid--realizados">
                @foreach($eventosRealizados as $arte)
                    <x-app.arte-realizada :arte="$arte" />
                @endforeach
            </div>
        </div>
    @endif

    <div class="container" id="sobre">
        <h2 class="block-header-title">
            SOBRE <span> NOS </span>
        </h2>

        <x-app.about />
    </div>

    <x-app.foot />

@endsection
