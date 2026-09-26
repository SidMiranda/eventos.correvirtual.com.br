# Cobrança com split no Mercado Pago (OAuth + `application_fee`)

Status: Implementado (aguardando a aplicação do Mercado Pago no .env) — pedido do dono em 2026-09-26 (MATRIX
`2026-09-26_0440_sidney_cobranca-split-mercado-pago`). Decisão: ADR 0008.

## Problema

A partir dos eventos de 2027 a plataforma retém uma taxa por inscrição paga
(R$ 0,70), descontada do valor da inscrição. Hoje o Pix sai com uma
credencial única no `.env` e o dinheiro cai 100% no organizador.

## Requisitos

**Organizador — tela "Cobrança"** (último item do menu, ícone de cifrão)
- [x] Sem conta conectada: explica e oferece "Conectar conta do Mercado Pago"
      (OAuth). Sem a aplicação configurada no `.env`, o botão explica que
      falta configurar, em vez de levar a um erro.
- [x] Ao autorizar, guarda `access_token` e `refresh_token` (cifrados), o id
      e o nome/e-mail da conta Mercado Pago dele e o vencimento.
- [x] Conectado: a tela vira informativa — "Sua conta está conectada. Você
      está recebendo na conta: …", as taxas (plataforma R$ 0,70 a partir dos
      eventos de 01/01/2027; tarifa do Mercado Pago no Pix ~0,99%) e o
      exemplo: R$ 100,00 → R$ 0,99 do Mercado Pago, R$ 0,70 da plataforma,
      organizador recebe R$ 98,31. O valor da taxa na tela vem da configuração.
- [x] Uma conta por organizador; reconectar substitui a anterior.

**Cobrança (Pix)**
- [x] Organizador **sem** conta conectada: exatamente como hoje (credencial
      do `.env`, sem taxa) — inclusive em evento de 2027, com aviso no log.
- [x] Organizador **com** conta conectada: Pix criado com o token dele.
      `application_fee` = taxa da configuração **só se o ano do evento ≥ 2027**.
- [x] A taxa nunca passa de uma fração do valor: inscrição que não comporta a
      taxa (valor ≤ taxa) sai sem taxa, com aviso no log.
- [x] O pagamento guarda por qual conta saiu e a taxa enviada.
- [x] O webhook consulta o pagamento com a conta que o criou e aceita a
      assinatura das duas aplicações (a de hoje e a da plataforma).

**Taxa configurável**
- [x] Tabela `platform_settings` (chave/valor), com
      `plataforma_taxa_inscricao = 0.70`. Mudar o valor não exige deploy nem
      nova autorização. Comando: `php artisan plataforma:taxa 0.80`.

**Token**
- [x] `php artisan mercadopago:renovar-tokens` renova quem vence em até 30
      dias; agendado todo dia. Na hora de cobrar, se o token vence em até 7
      dias, renova antes.
- [x] Falha na renovação ou na geração do Pix com conta conectada → alerta
      imediato (log crítico + e-mail para o endereço de alerta). A ponte com a
      MATRIX (`inbox/jade/` `urgente:`) depende de como o servidor chega lá —
      pergunta em aberto ao dono.

**Cupom de 100%**
- [x] Inscrição gratuita (cupom de 100% ou desconto de idade que zera) já é
      confirmada sem Pix (`ConfirmacaoDeInscricao`), e o `PixController`
      recusa valor zero. Cupom parcial segue o fluxo normal, com a taxa. Os
      testes passam a cobrir explicitamente que não há chamada ao Mercado Pago.

## Fora de escopo

- Tirar o modelo antigo (fica, desligado para quem conectou).
- Desconectar pelo painel (o organizador revoga no Mercado Pago; pode vir depois).
- Repassar a taxa ao atleta somando ao preço — a taxa sai do valor (decisão do dono).
- Cartão/boleto, estorno.
- Tarifa do Mercado Pago lida da API — fica fixa no texto.

## Design

### Tabelas

```
platform_settings      key (unique), value, timestamps
mercado_pago_contas    id, organizer_id (unique), mp_user_id, nome, email,
                       access_token (encrypted), refresh_token (encrypted),
                       public_key, live_mode, expires_at, connected_at,
                       refreshed_at, last_error, timestamps
payments  (+)          mercado_pago_conta_id (nullable), application_fee (nullable)
```

### Peças (`app/Services/Cobranca/`)

| Classe | Papel |
|---|---|
| `ConfiguracaoDaPlataforma` | lê/grava `platform_settings`, com padrão |
| `TaxaDaPlataforma` | `para(Subscription): ?float` — ano ≥ 2027, conta conectada, valor > taxa |
| `ContaDeCobranca` | valor com a credencial e a conta (ou nula = `.env`) |
| `EscolhaDeConta` | `paraOrganizador(id)` / `paraPagamento(Payment)` → `ContaDeCobranca` |
| `MercadoPagoOAuth` | URL de autorização, troca do código, renovação, `users/me` (HTTP) |
| `RenovacaoDeToken` | renova uma conta; em falha grava `last_error` e alerta |
| `AlertaDeCobranca` | log crítico + e-mail |

`MercadoPagoService::createPixPayment()` e `getPayment()` ganham um parâmetro
opcional de token (sem ele, o `.env` de sempre) e o `application_fee` opcional.

### OAuth

- `GET /admin/cobranca` (tela), `POST /admin/cobranca/conectar` (monta o
  `state` e redireciona ao Mercado Pago), `GET /mercadopago/oauth/retorno`
  (fora do prefixo do painel, é o `redirect_uri` fixo cadastrado na aplicação).
- O `state` é **cifrado** (organizador, usuário, domínio de origem, validade de
  15 min, nonce de uso único no cache): o retorno pode cair num domínio
  diferente do painel e sem a sessão, e mesmo assim só grava a conta do
  organizador que pediu. Depois volta ao `/admin/cobranca` do domínio de origem.

### Credenciais (`.env`, nada na MATRIX)

`MERCADOPAGO_APP_CLIENT_ID`, `MERCADOPAGO_APP_CLIENT_SECRET`,
`MERCADOPAGO_OAUTH_REDIRECT_URI`, `MERCADOPAGO_APP_WEBHOOK_SECRET`,
`ALERTA_COBRANCA_EMAIL`.

## Plano de testes

- `TaxaDaPlataformaTest` — 2026 sem taxa; 2027 com; sem conta sem taxa; valor
  que não comporta a taxa; valor da configuração.
- `CobrancaConectarTest` — tela nos três estados; `state` adulterado, vencido
  ou reutilizado recusado; retorno grava a conta do organizador certo com
  tokens cifrados; isolamento entre organizadores.
- `PixComSplitTest` — com conta: token do organizador + `application_fee`
  (2027) ou sem (2026); sem conta: `.env` e sem taxa; pagamento guarda conta e
  taxa; falha com conta conectada gera alerta.
- `WebhookComSplitTest` — consulta com a conta do pagamento; aceita as duas
  assinaturas; continua recusando assinatura inválida.
- `RenovacaoDeTokenTest` — renova quem vence; falha grava erro e alerta.
- Cupom de 100%: nenhuma chamada ao Mercado Pago.
