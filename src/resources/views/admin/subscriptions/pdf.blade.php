{{-- O relatório de inscritos de um evento.

     Folha A4 do dompdf: nada de flexbox, grid ou variável CSS — o motor não
     entende. Tabela e estilo inline simples, que é o que ele renderiza bem.

     Vai `inline` (ver SubscriptionController::pdf), então abre no visualizador
     do navegador em vez de baixar. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Inscritos — {{ $evento->title }}</title>
    <style>
        @page { margin: 22mm 14mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }

        .cabecalho { border-bottom: 2px solid #0d1b2a; padding-bottom: 8px; margin-bottom: 14px; }
        .organizador { font-size: 11px; color: #1a71b2; font-weight: bold; text-transform: uppercase; letter-spacing: .06em; }
        .titulo { font-size: 17px; font-weight: bold; color: #0d1b2a; margin: 4px 0 2px; }
        .subtitulo { font-size: 10px; color: #555; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #0d1b2a; color: #fff; font-size: 9px; text-transform: uppercase;
            letter-spacing: .04em; padding: 6px 5px; text-align: left;
        }
        tbody td { padding: 5px; border-bottom: 1px solid #e3e8ee; }
        tbody tr:nth-child(even) td { background: #f6f9fc; }
        .direita { text-align: right; }
        .centro { text-align: center; }
        .menor { font-size: 8px; color: #666; }

        .totais { margin-top: 16px; border-top: 2px solid #0d1b2a; padding-top: 8px; }
        .totais td { padding: 3px 5px; font-size: 10px; }
        .totais .rotulo { color: #555; }
        .totais .valor { font-weight: bold; text-align: right; }

        .rodape { margin-top: 14px; font-size: 8px; color: #888; text-align: center; }
        .vazio { padding: 24px; text-align: center; color: #666; }
    </style>
</head>
<body>

    <div class="cabecalho">
        <div class="organizador">{{ $organizador->name }}</div>
        <div class="titulo">{{ $evento->title }}</div>
        <div class="subtitulo">
            {{ $evento->event_date?->format('d/m/Y') }} · {{ $evento->location }}
            <br>
            Lista de inscritos — {{ $filtro->descricao() }}
        </div>
    </div>

    @if ($inscricoes->isEmpty())
        <p class="vazio">Nenhuma inscrição com esse filtro.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 26px;">#</th>
                    <th>Atleta</th>
                    <th style="width: 92px;">CPF</th>
                    <th style="width: 32px;" class="centro">PCD</th>
                    <th style="width: 96px;">Modalidade</th>
                    <th style="width: 96px;">Kit</th>
                    <th style="width: 72px;" class="direita">Valor</th>
                    <th style="width: 78px;" class="centro">Situação</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($inscricoes as $i => $inscricao)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            {{ $inscricao->user->name ?? 'Atleta removido' }}
                            <div class="menor">{{ $inscricao->user->email ?? '' }}</div>
                        </td>
                        <td>{{ $inscricao->user->cpf ?? '—' }}</td>
                        <td class="centro">{{ $inscricao->user?->is_pcd ? 'Sim' : '—' }}</td>
                        <td>{{ $inscricao->modality->name ?? '—' }}</td>
                        <td>{{ $inscricao->kit->name ?? '—' }}</td>
                        <td class="direita">
                            R$ {{ number_format($inscricao->price, 2, ',', '.') }}
                            @if ($inscricao->temDesconto())
                                <div class="menor">
                                    {{ $inscricao->coupon?->code ?? 'cupom' }}
                                    −{{ number_format($inscricao->discount_amount, 2, ',', '.') }}
                                </div>
                            @endif
                        </td>
                        <td class="centro">{{ $inscricao->situacao() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totais">
        <tr>
            <td class="rotulo">Inscrições no relatório</td>
            <td class="valor">{{ $totais['inscricoes'] }}</td>
            <td class="rotulo">Arrecadado (pagas)</td>
            <td class="valor">R$ {{ number_format($totais['arrecadado'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="rotulo">Pagas / aguardando / canceladas</td>
            <td class="valor">{{ $totais['pagas'] }} / {{ $totais['pendentes'] }} / {{ $totais['canceladas'] }}</td>
            <td class="rotulo">Descontos concedidos (pagas)</td>
            <td class="valor">R$ {{ number_format($totais['descontos'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="rodape">
        Emitido em {{ now()->format('d/m/Y \à\s H:i') }} · Corre Virtual
    </div>

</body>
</html>
