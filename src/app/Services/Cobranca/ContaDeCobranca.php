<?php

namespace App\Services\Cobranca;

use App\Models\MercadoPagoConta;

/**
 * Com que credencial se fala com o Mercado Pago: a conta conectada do
 * organizador (modelo novo) ou a do `.env` (modelo antigo). Quem cobra ou
 * consulta só pergunta isto — nunca decide sozinho (ADR 0008).
 */
final class ContaDeCobranca
{
    private function __construct(
        public readonly ?MercadoPagoConta $conta,
        private readonly ?string $token,
    ) {
    }

    public static function doEnv(): self
    {
        return new self(null, config('services.mercadopago.token'));
    }

    public static function daConta(MercadoPagoConta $conta): self
    {
        return new self($conta, $conta->access_token);
    }

    public function conectada(): bool
    {
        return $this->conta !== null;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function contaId(): ?int
    {
        return $this->conta?->id;
    }
}
