@props(['subscription'])

@php
    $event = $subscription->event;
    $imageRelativePath = 'images/events/' . $event->id . '/card-' . $event->banner_url;
    $hasImage = $event->banner_url && file_exists(public_path($imageRelativePath));
@endphp

<div class="registration-list-card">
    <div class="registration-list-card__image-wrapper">
        @if($hasImage)
            <img src="{{ asset($imageRelativePath) }}" class="event-card__image" alt="{{ $event->title }}">
        @else
            <img src="{{ asset('images/default/card.jpg') }}" class="event-card__image" alt="{{ $event->title }}">
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
            @elseif($subscription->status === 'cancelled')
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
