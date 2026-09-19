@props(['subscription'])

@php
    $event = $subscription->event;
@endphp

<div class="registration-list-card">
    {{-- A arte aparece inteira (object-fit: contain no CSS), sobre o degradê do
         evento. Antes era recortada para preencher a coluna, e o recorte de um
         cartaz retrato come justamente o nome e a data, que ficam no topo. --}}
    <div class="registration-list-card__image-wrapper" style="background: {{ $event->degrade() }};">
        @if($event->banner_url)
            <img src="{{ \App\Support\Arquivos::cardDoEvento($event) }}"
                 onerror="this.onerror=null;this.src='{{ \App\Support\Arquivos::cardPadrao() }}';"
                 class="event-card__image" alt="{{ $event->title }}">
        @else
            {{-- Sem arte, o nome sobre o degradê — mesma solução do card da
                 home, em vez de uma foto de banco de imagem que não é da prova. --}}
            <div class="default-card-overlay">
                <h3 class="event-card-overlay-title">{{ $event->title }}</h3>
            </div>
        @endif
    </div>
    <div class="registration-list-card__content">
        <div class="registration-list-card__header">
            <h4 class="registration-list-card__title">{{ $event->title }}</h4>
            @if($subscription->status === 'paid')
                <span class="status-badge status-active">Inscrição Confirmada</span>
            @elseif($subscription->status === 'pending')
                <span class="status-badge status-pending">Pagamento Pendente</span>
            @elseif($subscription->status === 'canceled')
                <span class="status-badge status-canceled">Cancelada</span>
            @else
                <span class="status-badge status-past">{{ ucfirst($subscription->status) }}</span>
            @endif
        </div>
        <div class="registration-list-card__info">
            <p>📍 {{ $event->location }}</p>
            <p>📅 {{ \Carbon\Carbon::parse($event->event_date)->format('d/m/Y \à\s H:i') }}</p>
            <p>
                🏃 {{ $subscription->modality->name ?? 'Modalidade a definir' }}
                @if($subscription->bib_number) | Peito: {{ $subscription->bib_number }} @endif
            </p>
            <p>🎒 {{ $subscription->kit->name ?? 'Kit a definir' }}</p>
            {{-- O valor: é o que o atleta precisa ver antes de clicar em pagar.
                 Com cupom, o desconto aparece embaixo; grátis, diz que é grátis. --}}
            <p class="registration-list-card__valor">
                💰
                @if ($subscription->gratuita())
                    <span>Gratuita
                        @if ($subscription->coupon) <small>(cupom {{ $subscription->coupon->code }})</small> @endif
                    </span>
                @else
                    <span>
                        R$ {{ number_format($subscription->price, 2, ',', '.') }}
                        @if ($subscription->temDesconto())
                            <small class="registration-list-card__desconto">
                                cupom {{ $subscription->coupon?->code ?? 'aplicado' }}: −R$ {{ number_format($subscription->discount_amount, 2, ',', '.') }}
                            </small>
                        @endif
                    </span>
                @endif
            </p>
        </div>
        <div class="registration-list-card__actions">
            @if($subscription->status === 'pending')
                <form action="{{ url('/subscription/cancel') }}" method="POST" style="display: inline-block;">
                    @csrf
                    <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                    <button type="submit" class="btn-danger-small" onclick="return confirm('Deseja realmente cancelar esta inscrição?')">Cancelar</button>
                </form>

                <form action="{{ Route::has('event-pay') ? route('event-pay') : url('/event-pay') }}" method="POST" style="display: inline-block;">
                    @csrf
                    <input type="hidden" name="subscription_id" value="{{ $subscription->id }}">
                    <button type="submit" class="btn-primary-small">Pagar Agora</button>
                </form>
            @endif

            <a href="/event/{{ $event->id }}" role="button" class="btn-secondary">Ver Detalhes</a>
        </div>
    </div>
</div>
