# Pagamento via Pix (Mercado Pago)

Status: Baseline (descreve o comportamento **atual**, bugs incluídos — ver `docs/backlog.md`)

## Problema

Depois de criar uma inscrição (`pending`), o atleta precisa pagar via Pix e ter a inscrição confirmada automaticamente quando o pagamento cair.

## Modelos envolvidos

- `Payment` (`subscription_id`, `provider` default `mercadopago`, `transaction_id`, `payment_method` default `pix`, `status` [`pending`,`approved`,`rejected`,`refunded`], `qr_code`, `qr_code_base64`, `ticket_url`, `expires_at`, `paid_at`, `payload` json)
- `App\Services\MercadoPagoService` — encapsula o SDK `mercadopago/dx-php`, autentica com `config('services.mercadopago.token')` (env `MERCADOPAGO_ACCESS_TOKEN`)

## Fluxo atual

### Gerar cobrança Pix
`POST /event-pay` (autenticado) → `PixController::generatePix`. Recebe `subscription_id` e busca a `Subscription` **do usuário logado** (`user_id = auth()->id()`; 404 se não for dele — até 2026-09-20 era um `find($id)` solto e qualquer usuário logado gerava Pix para qualquer inscrição). Recusa inscrição que não esteja `pending` e inscrição de valor zero (cupom de 100% já nasce confirmada e não passa por aqui — é a rede de segurança para o Mercado Pago nunca receber R$ 0,00). Chama `MercadoPagoService::createPixPayment($subscription->price, $user->email, $subscriptionId)` — `price` é o valor gravado na inscrição, **já com o desconto do cupom se houve** (ver `docs/specs/cupons-de-desconto.md`). Cria um `Payment` com `status = pending` e os dados de QR code retornados. Renderiza `subscriptions.generate-pix` com o QR code e o valor cobrado (com o cupom e o desconto, quando há).

### Cliente aguardando confirmação
A tela de QR code consulta `GET /api/subscriptions/{id}/status` (`PixController::checkStatus`) periodicamente (polling, a cada poucos segundos no frontend) só pra saber o `status` atual da `Subscription`.

### Confirmação via webhook
Mercado Pago notifica `POST /api/webhooks/mercadopago` → `MercadoPagoWebhookController::handle`:
1. Loga o payload recebido.
2. **Valida a assinatura** (`App\Services\MercadoPagoWebhookSignature::isValid`, corrigido em 2026-07-30 — BUG-004): lê os headers `x-signature`/`x-request-id` e o `data.id` da query string crua da notificação (via `Symfony\Component\HttpFoundation\HeaderUtils::parseQuery`, não `$request->query()` — PHP converte pontos em nome de query param pra underscore, então `data.id` viraria `data_id` se lido do jeito ingênuo), monta o manifest `id:{data.id};request-id:{x-request-id};ts:{ts};` e compara o HMAC-SHA256 (usando `MERCADOPAGO_WEBHOOK_SECRET`) com o `v1` do header. Se a assinatura for inválida ou o secret não estiver configurado, responde `401` e não processa nada — **falha fechada**.
3. Extrai o ID do pagamento (`data.id` ou `id`, dependendo do formato da notificação).
4. Consulta a API do Mercado Pago pra confirmar o status real do pagamento (`MercadoPagoService::getPayment`) — **não confia cegamente no payload da notificação**, isso está correto.
5. Se `status === 'approved'`, chama `App\Services\ConfirmacaoDeInscricao::confirmar($id)` (desde 2026-09-20 — antes o código vivia inline aqui), que faz um update **atômico e condicional** — `Subscription::where('id', $id)->where('status', '!=', 'paid')->update(['status' => 'paid', 'confirmed_at' => now()])` — e o webhook marca o `Payment` correspondente (por `transaction_id`) como `approved`. O `where('status', '!=', 'paid')` existe porque o Mercado Pago pode reenviar a mesma notificação (retry); sem essa condição, duas notificações quase simultâneas para a mesma inscrição mandariam o e-mail de confirmação (próximo passo) duplicado. O número de linhas afetadas pelo update (`0` ou `1`) é o que decide se o e-mail é enviado. O mesmo serviço é usado pela inscrição gratuita (cupom que zera o valor, ver `docs/specs/cupons-de-desconto.md`) — é uma confirmação só, por dois caminhos.
6. **E-mail de confirmação** (`App\Mail\SubscriptionConfirmed`, `resources/views/emails/subscription-confirmed.blade.php`) — só quando o update do passo 5 afetou `1` linha (ou seja, só na primeira vez que a inscrição vira `paid`). Enviado via `dispatch(fn () => ...)->afterResponse()`: a `Closure` só roda depois que a resposta HTTP já foi enviada ao Mercado Pago (usa o hook de `terminate()` do Laravel, funciona com PHP-FPM sem precisar de worker de fila — não há um rodando neste projeto, ver DEBT-010). Isso evita segurar o webhook esperando o SMTP (lento, e o Mercado Pago tem timeout e reenvia se demorar). Uma falha no envio do e-mail (SMTP fora, etc.) é só logada (`Log::error`) — nunca desfaz a confirmação do pagamento, que já aconteceu no passo 5. O e-mail mostra o valor cobrado e, quando houve cupom, o código e o desconto; na inscrição gratuita o texto diz que não houve cobrança.
7. Sempre responde `200` pro Mercado Pago parar de reenviar (exceto quando a assinatura falha — nesse caso é `401`, de propósito, pra não confirmar recebimento de uma notificação não autenticada).

O e-mail traz os dados do evento (título, data, local, modalidade, kit) e dois botões: "Ver minha inscrição" (`/my-subscriptions`) e "Adicionar à agenda" — link pro Google Calendar como evento de **dia inteiro** (`dates=YYYYMMDD/YYYYMMDD`, formato exigido pelo Google pra esse tipo de evento). Não usa horário porque `Event` não tem campo de horário de término — inventar uma duração seria informação errada na agenda do atleta. Sem anexo `.ics` por enquanto (mais compatível com Outlook/Apple Calendar, mas é escopo extra pra depois).

### Tela de sucesso
`GET /subscriptions/{id}/success` → `PixController::success`, puramente informativa.

## Bugs conhecidos nesta área

- **BUG-004 (corrigido em 2026-07-30):** o endpoint `/api/webhooks/mercadopago` não validava nenhum segredo/assinatura do Mercado Pago antes de consultar o pagamento e marcar como pago. Corrigido com validação de assinatura HMAC-SHA256 (ver "Confirmação via webhook" acima) — requer `MERCADOPAGO_WEBHOOK_SECRET` configurado, senão o webhook falha fechado.
- **BUG-001 (corrigido em 2026-08-02):** o valor cobrado no Pix era `Subscription.price` fixo em `0.05`; agora usa o preço real do `EventKit` (`App\Services\PixAmountResolver`). Uma flag temporária (`MERCADOPAGO_TEST_PRICE_ENABLED`) permite sobrepor esse valor de propósito durante a fase de teste — ver `docs/backlog.md`.
- **DEBT-005 (corrigido em 2026-08-03):** `MercadoPagoService::createPixPayment` não usa mais `dd()` no `catch` — loga o erro e devolve `null`; `PixController` mostra mensagem amigável de instabilidade.
- `PaymentsController::pay()` é um stub vazio sem rota — código morto (DEBT-001).

## Fora de escopo hoje

- Outros meios de pagamento além de Pix (cartão, boleto) — só Pix está integrado.
- Reembolso/estorno — o status `refunded` existe na coluna mas nada no código o define.
- Expiração automática de cobrança Pix vencida (`expires_at` é gravado mas nada limpa/expira a inscrição `pending` associada).
- Anexo `.ics` no e-mail de confirmação (só o link do Google Calendar por enquanto).
- Fila de e-mail de verdade (worker `queue:work`) — o envio depois da resposta (`afterResponse()`) resolve o problema de latência do webhook sem precisar disso agora, mas é uma melhoria futura (ver DEBT-010 em `docs/backlog.md`).

## Plano de testes

Cobertos:
- `App\Services\MercadoPagoWebhookSignature::isValid` — assinatura válida aceita, `v1`/`data.id`/secret errados ou ausentes rejeitados (`tests/Unit/MercadoPagoWebhookSignatureTest.php`).
- Webhook rejeita notificação sem assinatura válida ou sem secret configurado, sem alterar a `Subscription` (`tests/Feature/MercadoPagoWebhookControllerTest.php`).
- Webhook aprovado com assinatura válida marca `Subscription`/`Payment` corretos **e** envia `SubscriptionConfirmed` pro e-mail certo (`Mockery::mock('alias:...')` no `MercadoPagoService::getPayment`, `Mail::fake()` — `tests/Feature/MercadoPagoWebhookControllerTest.php`).
- Retry do webhook pra uma inscrição já `paid` não reenvia o e-mail (mesma suíte).
- `PixController@generatePix` cobra o valor do `EventKit` e trata falha da API do Mercado Pago com mensagem amigável, sem `dd()` (`tests/Feature/PixControllerTest.php`, `tests/Feature/PixAmountResolverTest.php`).
- `PixController@generatePix` recusa inscrição de outro usuário (404), já paga, de valor zero, e sem login (`tests/Feature/PixControllerTest.php`, 2026-09-20).
- Com cupom, o Pix é gerado com o valor já descontado (`tests/Feature/InscricaoComCupomTest.php`); o webhook aprovado preenche `confirmed_at`; `ConfirmacaoDeInscricao` confirma uma vez só (`tests/Unit/ConfirmacaoDeInscricaoTest.php`).

Ainda por escrever:
- Webhook não quebra (responde 200 e loga) quando o `subscription_id`/`transaction_id` não existe.
