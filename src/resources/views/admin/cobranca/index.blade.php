@extends('layouts.admin')

@section('titulo', 'Cobrança')
@section('icone', 'dollar-sign')
@section('subtitulo', 'A conta Mercado Pago em que você recebe as inscrições')

@php
    $taxaTxt = number_format($taxa, 2, ',', '.');
    // Exemplo com R$ 100,00 e a tarifa do Mercado Pago no Pix (0,99%): primeiro
    // sai a tarifa deles, depois a taxa da plataforma (ordem do Mercado Pago).
    $tarifaMp = 0.99;
    $exemplo = 100.00;
    $recebe = $exemplo - $tarifaMp - $taxa;
    $brl = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
@endphp

@section('conteudo')
<div class="row justify-content-center">
    <div class="col-xl-8">

        @if ($retorno === 'conectado')
            <div class="alert alert-success" role="alert">Conta do Mercado Pago conectada.</div>
        @elseif ($retorno === 'cancelado')
            <div class="alert alert-warning" role="alert">A conexão foi cancelada no Mercado Pago. Nada mudou.</div>
        @elseif ($retorno === 'erro')
            <div class="alert alert-danger" role="alert">Não foi possível conectar a conta agora. Tente de novo em alguns minutos.</div>
        @endif

        <div class="card mb-4">
            <div class="card-header">Conta Mercado Pago</div>
            <div class="card-body">
                @if ($conta)
                    <p class="lead mb-2">
                        <i data-feather="check-circle" class="text-success mr-1"></i>
                        Sua conta está conectada.
                    </p>
                    <p class="mb-1">Você está recebendo na conta: <strong>{{ $conta->identificacao() }}</strong></p>
                    <p class="small text-muted mb-0">Conectada em {{ $conta->connected_at?->format('d/m/Y \à\s H:i') }}.</p>
                    @if ($conta->last_error)
                        <div class="alert alert-danger mt-3 mb-0 small">
                            A última renovação da autorização falhou. Já avisamos a equipe da plataforma; se precisar, conecte de novo.
                        </div>
                    @endif
                @else
                    <p>Conecte a sua conta do Mercado Pago para receber as inscrições direto nela, com o Pix.</p>
                    @if ($configurado)
                        <form method="POST" action="{{ route('admin.cobranca.conectar') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i data-feather="link" class="mr-1"></i> Conectar conta do Mercado Pago
                            </button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">Você vai para o site do Mercado Pago, entra na sua conta e autoriza. Depois volta para cá.</p>
                    @else
                        <div class="alert alert-secondary mb-0 small">
                            A conexão com o Mercado Pago está sendo configurada pela equipe da plataforma. O botão aparece aqui assim que estiver pronta.
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Taxas</div>
            <div class="card-body">
                <p>
                    Eventos que acontecem a partir de <strong>01/01/{{ $aPartirDoAno }}</strong> têm
                    <strong>taxa de inscrição da plataforma de {{ $brl($taxa) }}</strong> por inscrição paga,
                    mais a <strong>tarifa do Mercado Pago</strong> (Pix: 0,99%).
                    Eventos de {{ $aPartirDoAno - 1 }} não têm taxa da plataforma.
                </p>
                <p class="mb-2">Exemplo, numa inscrição de {{ $brl($exemplo) }}:</p>
                <table class="table table-sm mb-2" style="max-width: 420px;">
                    <tbody>
                        <tr><td>Valor da inscrição</td><td class="text-right">{{ $brl($exemplo) }}</td></tr>
                        <tr><td>Tarifa do Mercado Pago (0,99%)</td><td class="text-right">− {{ $brl($tarifaMp) }}</td></tr>
                        <tr><td>Taxa da plataforma</td><td class="text-right">− {{ $brl($taxa) }}</td></tr>
                        <tr class="font-weight-bold"><td>Você recebe</td><td class="text-right">{{ $brl($recebe) }}</td></tr>
                    </tbody>
                </table>
                <p class="small text-muted mb-0">
                    O Mercado Pago desconta primeiro a tarifa dele e depois a taxa da plataforma.
                    Inscrição gratuita (cupom de 100%) não passa pelo Mercado Pago: não tem tarifa nem taxa.
                </p>
            </div>
        </div>

    </div>
</div>
@endsection
