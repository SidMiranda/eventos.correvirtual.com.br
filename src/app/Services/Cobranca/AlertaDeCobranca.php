<?php

namespace App\Services\Cobranca;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cobrança parada é dinheiro parado: avisa na hora (pedido do dono, ADR 0008).
 *
 * Hoje: log crítico + e-mail para ALERTA_COBRANCA_EMAIL. A ponte para a
 * MATRIX (inbox/jade, `urgente:`) depende de como o servidor chega lá — está
 * como pergunta ao dono (docs/specs/cobranca-split-mercado-pago.md).
 *
 * O mesmo problema avisa no máximo uma vez por hora: um token que não renova
 * falharia em todo Pix, e uma caixa lotada de alertas iguais é ignorada.
 */
final class AlertaDeCobranca
{
    public static function disparar(string $assunto, array $contexto = []): void
    {
        Log::critical('ALERTA DE COBRANÇA: ' . $assunto, $contexto);

        if (! Cache::add('alerta-cobranca:' . md5($assunto), true, now()->addHour())) {
            return;
        }

        $destino = config('services.alertas.cobranca_email');
        if (blank($destino)) {
            return;
        }

        try {
            $corpo = $assunto . "\n\n" . json_encode($contexto, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            Mail::raw($corpo, fn ($m) => $m->to($destino)->subject('[URGENTE] Cobrança: ' . $assunto));
        } catch (\Throwable $e) {
            // O alerta nunca derruba quem o chamou; o log crítico já ficou.
            Log::error('Não foi possível enviar o e-mail de alerta de cobrança: ' . $e->getMessage());
        }
    }
}
