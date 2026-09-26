<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Cobranca\ConexaoDaConta;
use App\Services\Cobranca\EstadoDoOAuth;
use App\Services\Cobranca\MercadoPagoOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * A volta do OAuth do Mercado Pago (o redirect_uri fixo da aplicação).
 *
 * Fora do painel de propósito: pode cair num domínio sem a sessão de quem
 * clicou. Quem diz de qual organizador é a volta é o state cifrado
 * (EstadoDoOAuth) — sem ele válido, nada é gravado.
 */
class MercadoPagoOAuthController extends Controller
{
    public function retorno(Request $request)
    {
        $estado = EstadoDoOAuth::conferir($request->query('state'));

        if ($estado === null) {
            Log::warning('Retorno do OAuth do Mercado Pago com state inválido, vencido ou repetido.');

            return response()->view('admin.cobranca.retorno-invalido', [], 400);
        }

        $destino = rtrim($estado['voltar'], '/') . '/admin/cobranca';

        // Quem pediu ainda administra aquele organizador? (pode ter perdido o
        // acesso nos minutos entre o clique e a volta)
        $usuario = User::find($estado['usuario']);
        if (! $usuario || $usuario->role !== 'organizer_admin' || (int) $usuario->organizer_id !== $estado['organizador']) {
            return redirect()->away($destino . '?mp=erro');
        }

        // O organizador clicou em "cancelar" no Mercado Pago.
        if ($request->filled('error') || blank($request->query('code'))) {
            return redirect()->away($destino . '?mp=cancelado');
        }

        try {
            $token = MercadoPagoOAuth::trocarCodigo((string) $request->query('code'));
            ConexaoDaConta::gravar($estado['organizador'], $token);
        } catch (\Throwable $e) {
            Log::error('Falha ao conectar a conta Mercado Pago do organizador.', [
                'organizer_id' => $estado['organizador'],
                'erro' => $e->getMessage(),
            ]);

            return redirect()->away($destino . '?mp=erro');
        }

        return redirect()->away($destino . '?mp=conectado');
    }
}
