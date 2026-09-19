@php
    $event = $subscription->event;
    $modality = $subscription->modality;
    $kit = $subscription->kit;

    // ->locale('pt_BR') força nomes de mês em português, independente do APP_LOCALE
    // configurado (hoje "en") — evita "Olá... confirmado... August" misturado.
    $eventDate = \Illuminate\Support\Carbon::parse($event->event_date)->locale('pt_BR');
    $dataFormatada = $eventDate->translatedFormat('d \d\e F \d\e Y, \à\s H:i');

    // Evento de dia inteiro no Google Calendar — sem horário de término cadastrado,
    // não inventamos duração. Formato exigido: YYYYMMDD/YYYYMMDD (fim exclusivo).
    $calStart = $eventDate->format('Ymd');
    $calEnd = $eventDate->copy()->addDay()->format('Ymd');

    $calendarUrl = 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE',
        'text' => $event->title,
        'dates' => "{$calStart}/{$calEnd}",
        'details' => "Sua inscrição na Corre Virtual: {$event->title} — modalidade {$modality->name}, kit {$kit->name}.",
        'location' => $event->location,
    ]);

    $minhasInscricoesUrl = url('/my-subscriptions');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0; padding:0; background:#eaf4fb; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eaf4fb; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 560px; background:#ffffff; border-radius: 12px; overflow: hidden;">
                    <tr>
                        <td style="background:#0d1b2a; padding: 24px 32px;">
                            <span style="color:#ffffff; font-size: 20px; font-weight: 800; letter-spacing: -0.02em;">Corre Virtual</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#1a71b2; padding: 20px 32px;">
                            <span style="color:#ffffff; font-size: 22px; font-weight: 800;">✅ Inscrição confirmada!</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 16px; font-size: 16px; color:#1a1a1a;">
                                Olá, <strong>{{ $subscription->user->name }}</strong>!
                            </p>
                            <p style="margin: 0 0 24px; font-size: 16px; color:#1a1a1a; line-height: 1.5;">
                                @if ($subscription->gratuita())
                                    Sua inscrição no evento abaixo está confirmada — com o cupom, não houve cobrança. Vamos juntos!
                                @else
                                    Seu pagamento foi aprovado e sua inscrição no evento abaixo está confirmada. Vamos juntos!
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eaf4fb; border-radius: 10px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin:0 0 12px; font-size:18px; font-weight:800; color:#0d1b2a;">{{ $event->title }}</p>
                                        <p style="margin:0 0 6px; font-size:14px; color:#1a1a1a;">📅 {{ $dataFormatada }}</p>
                                        <p style="margin:0 0 6px; font-size:14px; color:#1a1a1a;">📍 {{ $event->location }}</p>
                                        <p style="margin:0 0 6px; font-size:14px; color:#1a1a1a;">🏃 Modalidade: {{ $modality->name }}</p>
                                        <p style="margin:0 0 6px; font-size:14px; color:#1a1a1a;">🎒 Kit: {{ $kit->name }}</p>
                                        <p style="margin:0; font-size:14px; color:#1a1a1a;">
                                            💰 Valor:
                                            @if ($subscription->gratuita())
                                                gratuita
                                            @else
                                                R$ {{ number_format($subscription->price, 2, ',', '.') }}
                                            @endif
                                            @if ($subscription->coupon)
                                                — cupom {{ $subscription->coupon->code }}
                                                (−R$ {{ number_format($subscription->discount_amount, 2, ',', '.') }})
                                            @endif
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="padding: 0 8px;">
                                        <a href="{{ $minhasInscricoesUrl }}" style="display:inline-block; background:#1a71b2; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; padding:14px 24px; border-radius:999px;">Ver minha inscrição</a>
                                    </td>
                                    <td style="padding: 0 8px;">
                                        <a href="{{ $calendarUrl }}" style="display:inline-block; background:#ffffff; color:#0d1b2a; text-decoration:none; font-weight:700; font-size:14px; padding:14px 24px; border-radius:999px; border: 2px solid #0d1b2a;">📆 Adicionar à agenda</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 32px; background:#f3f3f3; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#666666;">Corre Virtual — {{ $event->location }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
