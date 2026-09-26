# 0008 — Cobrança como marketplace do Mercado Pago (OAuth + `application_fee`)

Status: Aceita

## Contexto

Até 2026 o Pix é gerado com **uma** credencial no `.env`
(`MERCADOPAGO_ACCESS_TOKEN`), que é a da conta Mercado Pago do organizador:
o dinheiro cai 100% nele, e a plataforma não recebe nada. A mesma credencial
serve a todo organizador do sistema — com um só ativo, nunca pesou.

Em 2026-09-26 o dono decidiu (MATRIX, `2026-09-26_0440_sidney_cobranca-split-mercado-pago`)
que, a partir dos eventos de **2027**, a plataforma retém uma **taxa por
inscrição paga** (R$ 0,70 no início), descontada do valor da inscrição.

## Decisão

1. **Modelo de marketplace do Mercado Pago.** A plataforma tem uma aplicação
   (na conta do Sidney); cada organizador **autoriza pela tela "Cobrança"**
   (OAuth). A cobrança é criada com o `access_token` do organizador e leva
   `application_fee`: o Mercado Pago desconta a tarifa dele, repassa a taxa à
   dona da aplicação e o resto ao organizador. A plataforma nunca custodia o
   dinheiro nem precisa de conta de destino no request.
2. **Os dois modelos convivem.** Organizador sem conta conectada continua no
   modelo antigo (credencial do `.env`, sem taxa), inclusive em evento de 2027.
   Conectou, o modelo novo vale para todos os eventos dele; o antigo fica
   desligado para ele, mas o código permanece.
3. **Taxa só em evento de 2027 em diante** (ano de `event_date`), com o valor
   lido de `platform_settings` (`plataforma_taxa_inscricao`) na hora de gerar
   o Pix — mudar o valor não exige deploy nem nova autorização.
4. **A escolha da credencial mora num lugar só** (`App\Services\Cobranca\*`):
   PixController e webhook perguntam "com que conta cobro/consulto este
   pagamento?", sem saber qual modelo está valendo.
5. **Tokens cifrados no banco** (cast `encrypted` com a `APP_KEY`), numa
   tabela própria por organizador (`mercado_pago_contas`), não em colunas de
   `organizers`: é um módulo ao lado, removível, e com histórico de renovação.
6. **O pagamento guarda o retrato da cobrança**: por qual conta saiu
   (`mercado_pago_conta_id`, nulo = modelo antigo) e a taxa enviada
   (`application_fee`). O webhook consulta o pagamento com a mesma conta que
   o criou.

## Consequências

- O webhook passa a aceitar a assinatura de **duas** aplicações: a de hoje
  (`MERCADOPAGO_WEBHOOK_SECRET`) e a da plataforma
  (`MERCADOPAGO_APP_WEBHOOK_SECRET`). Continua falhando fechado.
- O token do organizador vence (180 dias no Mercado Pago). Renovação
  automática por comando agendado **e** na hora de cobrar, se estiver perto de
  vencer. Falha vira alerta imediato — é dinheiro parando.
- A VPS passa a precisar do agendador do Laravel (`schedule:run` no cron).
- Cupom de 100% (ou desconto de idade que zera) segue sem passar pelo Mercado
  Pago: sem pagamento, sem taxa para ninguém.
