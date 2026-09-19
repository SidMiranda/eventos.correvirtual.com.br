@extends('layouts.app')

@section('title', 'Minhas Inscrições - Corre Virtual')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/top-bar.css') }}">
  {{-- ?v={{ filemtime(...) }}: sem isso a mudança de estilo só aparece para
       quem limpar o cache do navegador. --}}
  <link rel="stylesheet" href="{{ asset('css/my-subscriptions.css') }}?v={{ filemtime(public_path('css/my-subscriptions.css')) }}">
@endpush

@section('content')
    <div class="container">

        <x-app.response-message />

        @if($subscriptions->isEmpty())
            <div class="empty-container">
                <div class="empty-state-wrapper">
                <h2 class="my-registrations-title">Minhas Inscrições</h2>
                <div class="empty-registrations-card">
                    <div class="empty-icon">🏃‍♂️</div>
                    <p>Você ainda não possui nenhuma inscrição.</p>
                    <a href="{{ url('/') }}" class="btn-back-calendar">Voltar para o calendário</a>
                </div>
                </div>
            </div>
        @else

            <h2 class="my-registrations-title">Minhas Inscrições</h2>

            <h3 class="section-title">Eventos Inscritos</h3>
            <div class="registrations-list">

                @foreach($subscriptions as $subscription)
                <x-app.my-subscriptions :subscription="$subscription" />
                @endforeach
            </div>

        @endif
    </div>

    <!-- Modal Animado de Inscrição -->
    @if(session('modal_type'))
        <div id="subscriptionModal" class="subscription-modal-overlay">
            <div class="subscription-modal-content">
                @if(session('modal_type') === 'success')
                    <svg class="anim-icon success-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="anim-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="anim-check" d="M14.1 27.2l7.1 7.2 16.7-16.8" fill="none"/>
                    </svg>
                    {{-- Usamos o explode para pegar apenas o primeiro nome do atleta --}}
                    <h3>Parabéns, {{ explode(' ', session('user_name', 'Atleta'))[0] }}!</h3>
                    @if (session('inscricao_gratuita'))
                        {{-- Cupom zerou o valor: não há Pix nem "pagar agora" — a
                             inscrição já nasce confirmada, e o texto precisa dizer isso. --}}
                        <p>Sua inscrição no evento <br><strong>{{ session('event_title') }}</strong><br> está confirmada — com o cupom, não há nada a pagar.</p>
                    @else
                        <p>Sua inscrição no evento <br><strong>{{ session('event_title') }}</strong><br> foi realizada com sucesso!</p>
                    @endif
                @elseif(session('modal_type') === 'cancel')
                    <svg class="anim-icon cancel-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="anim-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="anim-cross" d="M16 16 L36 36" fill="none"/>
                        <path class="anim-cross" d="M36 16 L16 36" fill="none"/>
                    </svg>
                    <h3>Inscrição Cancelada</h3>
                    <p>Sua inscrição no evento <br><strong>{{ session('event_title') }}</strong><br> foi excluída.</p>
                @else
                    <svg class="anim-icon info-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="anim-circle" cx="26" cy="26" r="25" fill="none"/>
                        <path class="anim-line" d="M26 14v16" fill="none"/>
                        <circle class="anim-dot" cx="26" cy="38" r="2.5" stroke="none"/>
                    </svg>
                    <h3>Atenção, {{ explode(' ', session('user_name', 'Atleta'))[0] }}!</h3>
                    <p>Você já está inscrito no evento <br><strong>{{ session('event_title') }}</strong>.</p>
                @endif
            </div>
        </div>

        <style>
            .subscription-modal-overlay {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0, 0, 0, 0.65); backdrop-filter: blur(6px);
                display: flex; align-items: center; justify-content: center;
                z-index: 99999;
                animation: fadeInModal 0.3s ease-out forwards;
            }
            .subscription-modal-content {
                background: #fff; padding: 2.5rem 2rem; border-radius: 16px;
                text-align: center; max-width: 90%; width: 420px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                transform: scale(0.8);
                animation: scaleUpModal 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            }
            .subscription-modal-content h3 {
                margin: 1.25rem 0 0.5rem 0; font-size: 1.5rem; color: #1e293b;
            }
            .subscription-modal-content p {
                color: #475569; font-size: 1.05rem; line-height: 1.5; margin: 0;
            }
            .anim-icon { width: 80px; height: 80px; margin: 0 auto; display: block; }
            .anim-circle {
                stroke-width: 3; stroke-dasharray: 166; stroke-dashoffset: 166;
                animation: dashCircle 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
            }
            /* Success Theme */
            .success-icon .anim-circle, .success-icon .anim-check { stroke: #10b981; }
            .anim-check {
                stroke-width: 4; stroke-dasharray: 48; stroke-dashoffset: 48; stroke-linecap: round; stroke-linejoin: round;
                animation: dashCheck 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
            }
            /* Cancel Theme */
            .cancel-icon .anim-circle, .cancel-icon .anim-cross { stroke: #ef4444; }
            .anim-cross {
                stroke-width: 4; stroke-dasharray: 30; stroke-dashoffset: 30; stroke-linecap: round;
                animation: dashCheck 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
            }
            /* Info Theme */
            .info-icon .anim-circle, .info-icon .anim-line { stroke: #f59e0b; }
            .info-icon .anim-dot { fill: #f59e0b; }
            .anim-line {
                stroke-width: 4; stroke-dasharray: 20; stroke-dashoffset: 20; stroke-linecap: round;
                animation: dashCheck 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
            }
            .anim-dot { opacity: 0; animation: fadeDot 0.3s ease-out 0.8s forwards; }

            @keyframes fadeInModal { to { opacity: 1; } }
            @keyframes scaleUpModal { to { transform: scale(1); } }
            @keyframes dashCircle { to { stroke-dashoffset: 0; } }
            @keyframes dashCheck { to { stroke-dashoffset: 0; } }
            @keyframes fadeDot { to { opacity: 1; } }

            .modal-fade-out { animation: fadeOutModal 0.5s ease-in forwards; }
            @keyframes fadeOutModal { to { opacity: 0; visibility: hidden; } }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('subscriptionModal');
                if (modal) {
                    // O modal fica visível por 2.8s antes de iniciar a transição de saída (fade out)
                    setTimeout(() => {
                        modal.classList.add('modal-fade-out');
                        // Remove do DOM após o fade out terminar
                        setTimeout(() => { modal.remove(); }, 500);
                    }, 2800);
                }
            });
        </script>
    @endif
@endsection
