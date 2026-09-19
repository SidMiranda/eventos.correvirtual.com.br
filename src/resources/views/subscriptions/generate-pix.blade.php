<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pagamento PIX</title>
<link rel="stylesheet" href="{{ asset('css/teste-pix.css') }}">
<link rel="stylesheet" href="{{ asset('css/generate-pix.css') }}">

</head>

<body>

<div class="container">

<h1>Pague com PIX</h1>

<p class="subtitle">
Escaneie o QR Code ou copie o código abaixo para pagar sua inscrição
</p>

{{-- O valor cobrado, na tela do pagamento: com cupom, é aqui que o atleta
     confere que o desconto chegou no Pix. --}}
<p class="valor-pix" style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #0d1b2a;">
    Valor: R$ {{ number_format($subscription->price, 2, ',', '.') }}
    @if ($subscription->temDesconto())
        <span style="display: block; font-size: 14px; font-weight: 400; color: #475569;">
            R$ {{ number_format($subscription->list_price, 2, ',', '.') }}
            com cupom {{ $subscription->coupon?->code ?? 'aplicado' }}
            (−R$ {{ number_format($subscription->discount_amount, 2, ',', '.') }})
        </span>
    @endif
</p>

<div class="qrcode">
<img src="data:image/png;base64,{{ $pix->point_of_interaction->transaction_data->qr_code_base64 }}">
</div>

<textarea id="pixCode" class="pix-code" rows="4">
{{ $pix->point_of_interaction->transaction_data->qr_code }}
</textarea>

<button class="copy-btn" onclick="copiarPix()">
Copiar código PIX
</button>

<p class="status">
Pagamento aguardando confirmação...
</p>

<a class="ticket-link" href="{{ $pix->point_of_interaction->transaction_data->ticket_url }}" target="_blank">
Abrir página do pagamento
</a>

</div>

<script>

function copiarPix(){
    const campo = document.getElementById("pixCode");
    const textoPix = campo.value.trim();

    // Tenta usar a API moderna primeiro (funciona bem se tiver HTTPS/Ngrok)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textoPix).then(() => {
            alert("Código PIX copiado com sucesso!");
        }).catch(err => fallbackCopy(campo));
    } else {
        // Fallback: Modo antigo para navegadores que bloqueiam a API moderna
        fallbackCopy(campo);
    }
}

function fallbackCopy(campo) {
    campo.select();
    campo.setSelectionRange(0, 99999); // Para mobile
    document.execCommand("copy");
    alert("Código PIX copiado com sucesso!");
}

// Função que verifica o status no servidor a cada 3 segundos
function checkPaymentStatus() {
    fetch(`/api/subscriptions/{{ $subscriptionId }}/status`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'paid') {
                window.location.href = `/subscriptions/{{ $subscriptionId }}/success`;
            }
        })
        .catch(error => console.error('Erro ao verificar status:', error));
}

setInterval(checkPaymentStatus, 3000); // 3000 milissegundos = 3 segundos
</script>

</body>
</html>
