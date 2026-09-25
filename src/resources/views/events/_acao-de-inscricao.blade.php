{{-- A ação de inscrição da página do evento: aparece no alto da coluna e,
     de novo, no fim da página. Um trecho só, para as duas nunca divergirem.

     $completo = false (o de baixo) mostra só botão ou situação — os avisos de
     encerrado/realizado já estão no alto, repetir seria ruído.

     Prova já realizada ou com prazo encerrado não oferece inscrição: o botão
     levaria a um formulário que não vai aceitar nada. Esconder aqui é só a
     metade visível — quem barra de verdade é o SubscribeController. --}}
@if ($event->jaAconteceu())
  @if ($completo)
    <div class="event-aviso event-aviso--realizado">
      <strong>Evento realizado</strong>
      <span>Aconteceu em {{ $event->event_date->format('d/m/Y') }}.</span>
    </div>
  @endif
@elseif ($minhaInscricao)
  {{-- Já inscrito: a situação vale mesmo com as inscrições encerradas — quem
       pagou quer ver "confirmada", não "encerradas". --}}
  @if ($minhaInscricao->status === 'paid')
    <a href="{{ route('subscriptions.my') }}" class="cta-button cta-button--confirmada {{ $completo ? '' : 'cta-button--rodape' }}">
      Inscrição confirmada
    </a>
  @else
    <a href="{{ route('subscriptions.my') }}" class="cta-button cta-button--pendente {{ $completo ? '' : 'cta-button--rodape' }}">
      Aguardando pagamento
    </a>
  @endif
@elseif ($event->aceitaInscricao())
  <a href="/subscribe/event/{{ $event->id }}" class="cta-button {{ $completo ? '' : 'cta-button--rodape' }}">
    Inscreva-se
  </a>
@elseif (! $completo)
  {{-- nada no rodapé --}}
@elseif (! $event->inscricoesAbertas())
  <div class="event-aviso">
    <strong>Inscrições encerradas</strong>
    <span>O prazo terminou em {{ $event->registration_deadline->format('d/m/Y \à\s H:i') }}.</span>
  </div>
@else
  {{-- Datas abertas, mas nenhum lote vigente (ADR 0007): não é
       "encerradas" — é "ainda não". Se há lote futuro, diz quando. --}}
  <div class="event-aviso">
    <strong>Inscrições ainda não abertas</strong>
    @if ($proximo = $event->proximoLote())
      <span>Abrem em {{ $proximo->starts_at->format('d/m/Y \à\s H:i') }}.</span>
    @else
      <span>Em breve.</span>
    @endif
  </div>
@endif
