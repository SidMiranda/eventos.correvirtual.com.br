# 0007 — Preço por modalidade × kit × lote, com desconto por idade em cascata

Status: Aceita

## Contexto

Desde a origem do projeto o preço de uma inscrição é o preço do **kit**
(`event_kits.price`), igual em qualquer modalidade e em qualquer data. Em
2026-09-23, um dia depois do lançamento, o dono trouxe o briefing do
organizador com três exigências que esse modelo não atende: cobrar diferente
por distância, subir o preço por lote com virada automática, e dar desconto
por faixa etária. Junto veio a exigência de o atleta não conseguir escolher
uma modalidade e comprar o kit de outra.

Restrição dada de olhos abertos: o sistema está no ar com inscrição real.
Migração tem de ser aditiva e nada pode parar.

## Decisão

1. **O preço passa a ser função de (modalidade, kit, lote)**, numa tabela
   própria (`event_prices`). O kit deixa de ter preço próprio no checkout;
   `event_kits.price` continua na tabela, sem uso, como dado histórico.
2. **Lote é janela, não produto.** `event_lots` guarda vigência e limite; o
   sistema resolve o lote vigente na hora da inscrição, sem ativação manual.
   Sem lote vigente, não se vende.
3. **Kit pertence a modalidades** (N:N, `event_kit_modality`). O atleta só vê
   os kits da modalidade que escolheu.
4. **Variação do kit** em `kit_options` (`attribute`, `value`). Só
   `shirt_size` é implementado; a estrutura aceita cor sem migration nova.
5. **Categoria etária é desconto, não kit** (`age_categories`), calculada
   pela data de nascimento do cadastro segundo o critério do evento
   (`calendar_year` padrão | `exact_date`). Em mais de uma categoria, vale a
   de maior desconto em reais.
6. **Descontos em cascata**: a idade abate o preço base; o cupom abate o que
   sobrou. Decisão do dono em 2026-09-23 entre quatro opções apresentadas
   (cascata, soma sobre a base, só o maior, cupom bloqueado com categoria).
7. **O retrato financeiro na inscrição ganha `age_discount_amount`** ao lado
   de `discount_amount` (cupom). Um balaio de descontos numa coluna só
   inviabilizaria o financeiro futuro.
8. **Entrega em fatias**: estrutura + painel primeiro (checkout intocado),
   fluxo do atleta depois, menu por último. A grade migrada é conferida em
   produção antes de qualquer atleta ser cobrado por ela.

## Alternativas consideradas

- **Preço na modalidade** (o que o organizador pediu em palavras). Não
  cobre "kit completo custa mais que kit básico" na mesma distância. A
  tabela por combinação atende os dois lados.
- **Lote com preço próprio** (lote como produto). Duplicaria a grade a cada
  lote e obrigaria a cadastrar preço por lote mesmo quando só uma combinação
  muda. Lote como coluna da grade é o que o organizador desenha no papel.
- **Kit com `modality_id` (1:N)**. Simples, mas a compatibilidade exigia
  "kit existente disponível em todas as modalidades", que é N:N por
  definição.
- **Tamanho como coluna `event_kits.shirt_sizes` (JSON)**. Menos tabela, mas
  fecha a porta para cor e não permite `unique` por valor. `kit_options`
  custa uma tabela e resolve os dois.
- **Somar os dois descontos sobre a base.** Desconta mais e pode zerar com
  percentuais altos. O dono escolheu a cascata.
- **Apagar `event_kits.price`.** Migração destrutiva num sistema no ar, e a
  coluna documenta de onde vieram os preços migrados. Fica.

## Consequências

- `PrecoDaInscricao` deixa de receber um kit e passa a receber um preço base,
  uma categoria opcional e um cupom opcional. Quem chamava com kit precisa
  primeiro resolver lote e tabela.
- Todo teste que inscreve alguém precisa de lote, vínculo kit↔modalidade e
  preço na tabela. `PrecosLegados::migrarEvento()` faz isso em uma linha e é
  exatamente o caminho que produção percorreu — usar nos testes é
  reaproveitar, não atalhar.
- Evento novo sem lote não vende. O painel avisa na grade de preços e a página
  pública diz "inscrições ainda não abertas". É comportamento, não bug.
- `Event::inscricoesAbertas()` continua sendo a regra de datas; a regra de
  lote vive em `Event::aceitaInscricao()`. As listas do painel não pagam uma
  consulta de lote por linha.
- O campo solto `subscriptions.shirt_size` de 2026-09-22 continua sendo a
  coluna onde o tamanho é gravado — o que muda é a origem (o kit) e a
  obrigatoriedade (só quando o kit tem tamanhos).
