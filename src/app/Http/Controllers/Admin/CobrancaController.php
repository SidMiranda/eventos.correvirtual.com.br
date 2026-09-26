<?php

namespace App\Http\Controllers\Admin;

use App\Services\Cobranca\ConfiguracaoDaPlataforma;
use App\Services\Cobranca\EstadoDoOAuth;
use App\Services\Cobranca\MercadoPagoOAuth;
use App\Services\Cobranca\TaxaDaPlataforma;
use Illuminate\Http\Request;

/**
 * "Cobrança": o organizador conecta a conta Mercado Pago dele (OAuth) e, depois
 * disso, vê em que conta está recebendo e quanto sai de taxa (ADR 0008,
 * docs/specs/cobranca-split-mercado-pago.md).
 */
class CobrancaController extends AdminController
{
    public function index(Request $request)
    {
        return view('admin.cobranca.index', [
            'conta' => $this->organizer()->mercadoPagoConta,
            'configurado' => MercadoPagoOAuth::configurado(),
            'taxa' => ConfiguracaoDaPlataforma::taxaDeInscricao(),
            'aPartirDoAno' => TaxaDaPlataforma::A_PARTIR_DO_ANO,
            // A volta do OAuth pode cair em outro domínio (sem a sessão), então
            // o resultado chega pela URL, e não por flash.
            'retorno' => $request->query('mp'),
        ]);
    }

    public function conectar(Request $request)
    {
        abort_unless(MercadoPagoOAuth::configurado(), 503, 'A conexão com o Mercado Pago ainda não foi configurada.');

        $state = EstadoDoOAuth::gerar(
            $this->organizerId(),
            (int) auth()->id(),
            // Volta para o painel no mesmo domínio em que o botão foi clicado.
            $request->getSchemeAndHttpHost()
        );

        return redirect()->away(MercadoPagoOAuth::urlDeAutorizacao($state));
    }
}
