{{-- Uma inscrição na "Minha conta". Linha compacta com miniatura, e não o
     cartaz inteiro: no celular, três cartazes em pé eram três telas de rolagem
     para achar o botão de pagar. --}}
@php
    $event = $inscricao->event;
    $realizada = $event->jaAconteceu();
    $selo = match ($inscricao->status) {
        'paid' => ['Confirmada', 'paga'],
        'pending' => ['Aguardando pagamento', 'pendente'],
        'cancelled' => ['Cancelada', 'cancelada'],
        default => [ucfirst($inscricao->status), 'neutra'],
    };
@endphp

<article class="insc {{ $realizada ? 'insc--realizada' : '' }}">
    <a class="insc__thumb" href="/event/{{ $event->id }}" style="background: {{ $event->degrade() }};" aria-hidden="true" tabindex="-1">
        @if ($event->banner_url)
            <img src="{{ \App\Support\Arquivos::cardDoEvento($event) }}" alt="" loading="lazy"
                 onerror="this.remove();">
        @endif
    </a>

    <div class="insc__corpo">
        <h3 class="insc__titulo">{{ $event->title }}</h3>
        <p class="insc__meta">{{ $event->event_date?->format('d/m/Y \à\s H:i') }}</p>
        <p class="insc__meta">
            {{ $inscricao->modality->name ?? 'Modalidade a definir' }} · {{ $inscricao->kit->name ?? 'Kit a definir' }}
            @if ($inscricao->bib_number) · Peito {{ $inscricao->bib_number }} @endif
        </p>
        @if ($inscricao->shirt_size || $inscricao->team_name)
            <p class="insc__meta">
                @if ($inscricao->shirt_size) Camiseta {{ \App\Models\Subscription::nomeDoTamanho($inscricao->shirt_size) }} @endif
                @if ($inscricao->shirt_size && $inscricao->team_name) · @endif
                @if ($inscricao->team_name) Equipe {{ $inscricao->team_name }} @endif
            </p>
        @endif

        <div class="insc__rodape">
            <span class="selo selo--{{ $selo[1] }}">{{ $selo[0] }}</span>

            {{-- O valor: é o que o atleta precisa ver antes de pagar. Com
                 desconto, de onde ele veio; grátis, diz que é grátis. --}}
            <span class="insc__valor">
                @if ($inscricao->gratuita())
                    Gratuita
                    @if ($inscricao->coupon) <small>cupom {{ $inscricao->coupon->code }}</small>
                    @elseif ($inscricao->ageCategory) <small>{{ $inscricao->ageCategory->name }}</small> @endif
                @else
                    R$ {{ number_format($inscricao->price, 2, ',', '.') }}
                    @if ($inscricao->temDescontoDeIdade())
                        <small>{{ $inscricao->ageCategory?->name ?? 'categoria' }}: −R$ {{ number_format($inscricao->age_discount_amount, 2, ',', '.') }}</small>
                    @endif
                    @if ($inscricao->temDesconto())
                        <small>cupom {{ $inscricao->coupon?->code ?? 'aplicado' }}: −R$ {{ number_format($inscricao->discount_amount, 2, ',', '.') }}</small>
                    @endif
                @endif
            </span>
        </div>
    </div>

    <div class="insc__acoes">
        @if ($inscricao->status === 'pending' && ! $realizada)
            <form action="{{ route('event-pay') }}" method="POST">
                @csrf
                <input type="hidden" name="subscription_id" value="{{ $inscricao->id }}">
                <button type="submit" class="botao botao--primario">Pagar Agora</button>
            </form>
            <form action="{{ route('subscriptions.cancel') }}" method="POST">
                @csrf
                <input type="hidden" name="subscription_id" value="{{ $inscricao->id }}">
                <button type="submit" class="botao botao--perigo" onclick="return confirm('Deseja realmente cancelar esta inscrição?')">Cancelar</button>
            </form>
        @endif
        <a href="/event/{{ $event->id }}" class="botao botao--neutro">Ver evento</a>
    </div>
</article>
