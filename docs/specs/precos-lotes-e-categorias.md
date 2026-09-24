# Preço por modalidade × kit × lote, categoria etária e variação do kit

Status: Em implementação (2026-09-23) — fatia 1 (estrutura + painel) primeiro; fatia 2 (fluxo do atleta) depois

## Problema

Hoje o preço mora no kit (`event_kits.price`) e é o mesmo em qualquer
modalidade e em qualquer data. O organizador quer três coisas que isso não
permite:

1. **Cobrar diferente por modalidade.** 10K custa mais que 5K mesmo com o
   mesmo kit — e hoje ele precisa cadastrar "Kit Básico 5K" e "Kit Básico 10K"
   como dois kits, o que deixa o atleta escolher o 5K e comprar o kit do 10K.
2. **Lote.** O preço sobe conforme a prova se aproxima, sem ninguém precisar
   entrar no painel na virada.
3. **Desconto por idade.** Criança e idoso recebem o mesmo kit e pagam menos.

E uma quarta, que apareceu em 2026-09-22: o **tamanho da camiseta** virou um
campo solto na inscrição, igual para todos os kits — inclusive para o kit
"sem camiseta". Precisa ser variação do kit.

Briefing do dono em 2026-09-23 (`briefing-inscricao-corrida-virtual.md`,
anexado na conversa). Diretriz explícita: evoluir o que existe, migrações
aditivas, e nada do que está no ar pode parar.

## Requisitos

**Organizador**
- [ ] Cadastra **lotes** do evento: nome, início, fim (opcional = aberto),
      quantidade máxima (opcional), ordem.
- [ ] Cadastra **categorias etárias**: nome, idade mínima e/ou máxima, tipo
      (percentual ou valor) e valor do desconto.
- [ ] Define o **critério de idade** do evento: ano-calendário (padrão) ou
      data exata.
- [ ] Vincula cada **kit às modalidades** em que ele pode ser comprado.
- [ ] Define por kit os **tamanhos de camiseta** disponíveis.
- [ ] Preenche a **grade de preços**: linhas = modalidade × kit, colunas =
      lotes, célula = preço.
- [ ] Nada disso vaza entre organizadores (mesma regra do resto do painel).

**Atleta** (fatia 2)
- [ ] Escolhe a modalidade e só vê os kits daquela modalidade.
- [ ] Se o kit tem tamanhos, escolhe um — e é obrigatório. Se não tem, o campo
      não aparece.
- [ ] Vê o preço do lote vigente, o desconto de idade (se cair em categoria) e
      o total, antes de confirmar.
- [ ] Sem lote vigente, as inscrições estão fechadas.
- [ ] A inscrição guarda: modalidade, kit, tamanho, lote, preço base, desconto
      de idade, desconto de cupom e valor final.

**Compatibilidade**
- [ ] Evento já cadastrado ganha "Lote 1" aberto, e o preço atual de cada kit
      vira o preço de cada (modalidade, kit) nesse lote.
- [ ] Kit existente fica disponível em todas as modalidades do evento.
- [ ] Inscrição já feita não muda em nada.

## Fora de escopo

- Cupom continua como está — só se combina com o desconto de idade (ver
  "cascata" abaixo).
- Estoque por tamanho.
- Inscrição em grupo.
- Relatórios.
- Outras variações do kit além de tamanho (a tabela `kit_options` já aceita,
  mas só `shirt_size` é implementado).
- **"Configurações do evento" como tela própria.** O briefing lista no menu;
  como o único parâmetro é o critério de idade, ele entra no formulário do
  evento ("Dados do evento"). Uma tela para um botão de rádio não é fluxo, é
  cerimônia.

## Design

### Tabelas novas

```
event_lots            id, event_id, name, starts_at, ends_at (null = aberto),
                      max_subscriptions (null = sem limite), position, active
event_prices          id, event_id, modality_id, kit_id, lot_id, price
                      unique (modality_id, kit_id, lot_id)
event_kit_modality    kit_id, modality_id  — unique
kit_options           id, kit_id, attribute ('shirt_size'), value, position
                      unique (kit_id, attribute, value)
age_categories        id, event_id, name, min_age, max_age,
                      discount_type ('percent'|'amount'), discount_value, active
```

Colunas novas: `events.age_criteria` (`calendar_year` padrão | `exact_date`);
em `subscriptions`: `lot_id`, `age_category_id`, `age_discount_amount`.

`event_kits.price` **continua existindo**. Migração aditiva: apagar coluna com
dado histórico num sistema no ar não se faz. Depois da fatia 2 ela deixa de
ser lida no checkout; o formulário do kit deixa de mostrá-la.

### O retrato financeiro na inscrição

| Coluna | Significado |
|---|---|
| `list_price` | preço base da tabela (modalidade, kit, lote) |
| `age_discount_amount` | o que a categoria etária abateu |
| `discount_amount` | o que o **cupom** abateu (significado inalterado) |
| `price` | o cobrado: `list_price − age_discount_amount − discount_amount` |

`discount_amount` não vira um balaio de descontos de propósito: o financeiro
que vier precisa saber de onde saiu cada abatimento.

**Cascata** (decisão do dono, 2026-09-23): a idade abate o preço base; o cupom
abate **o que sobrou**. R$ 100 com Idoso −50% e cupom −20% = R$ 40. Nunca
negativo; o cupom continua com teto no valor sobre o qual incide.

Toda a conta continua em centavos inteiros (`PrecoDaInscricao`).

### Lote vigente

Resolvido pelo sistema na hora, sem "ativar" manual. Entre os lotes ativos do
evento com `starts_at <= agora` e (`ends_at` nulo ou `> agora`), descarta os
que atingiram `max_subscriptions` (contando inscrições não canceladas com
aquele `lot_id`) e fica com o de menor `position`, depois o de início mais
antigo.

Sem lote vigente = inscrições fechadas. Se existe lote **futuro**, a página do
evento diz "abrem em {data}" em vez de "encerradas". Continua valendo o
`registration_deadline` e a data do evento — as duas regras se somam.

### Categoria etária

A data de nascimento vem do cadastro (`users.birth_date`); a inscrição não
pergunta. Idade pelo critério do evento:

- `calendar_year`: `ano(event_date) − ano(birth_date)`. Quem faz 60 em
  dezembro conta 60 em janeiro.
- `exact_date`: anos completos na data do evento.

Cai em mais de uma categoria → vale a de **maior desconto em reais** sobre o
preço base (é a única forma de comparar percentual com valor fixo). Em
nenhuma → integral. Sem data de nascimento → integral.

### Kit ↔ modalidade e variação do kit

`EventKit::modalities()` (N:N). O kit só aparece para o atleta nas modalidades
em que está vinculado. No formulário do kit: caixas de seleção das
modalidades do evento, ao menos uma obrigatória quando o evento tem
modalidades.

`EventKit::tamanhos()` = `kit_options` com `attribute = 'shirt_size'`. O
formulário oferece a tabela de medidas que já existe
(`Subscription::CAMISETAS` + `CAMISETAS_BABY_LOOK`) como caixas de seleção.
Kit sem tamanho marcado = não pede tamanho.

### Grade de preços (`/admin/eventos/{id}/precos`)

Um formulário só: `precos[modalidade][kit][lote] = valor`. Linhas = cada par
(modalidade, kit) **em que o kit está vinculado à modalidade**; colunas = lotes
por ordem. Célula vazia = combinação não vendável naquele lote. Salvar faz
upsert do preenchido e apaga o vazio. Sem lote, sem kit ou sem modalidade, a
tela explica o que falta e aponta para a aba certa.

### Migração dos dados (`App\Support\PrecosLegados`)

Roda na migration e fica disponível como `php artisan eventos:migrar-precos`
(idempotente). Para cada evento:

1. Sem lote → cria "Lote 1", `starts_at = agora`, `ends_at = null`, posição 0.
2. Cada kit sem vínculo → vinculado a **todas** as modalidades do evento.
3. Cada (modalidade, kit) sem preço no lote → `event_kits.price`.
4. Cada kit sem tamanho → recebe os 10 tamanhos da tabela, **exceto** se o nome
   contém "sem camiseta" (caso real em produção). É heurística, e é a única:
   sem ela, o kit "Sem camiseta" passaria a exigir tamanho no dia seguinte ao
   lançamento. O organizador ajusta no formulário do kit.

A lógica mora numa classe de suporte e não dentro da migration para poder ser
**testada**: o teste monta um evento na estrutura antiga, roda, e confere que
o preço de cada combinação é exatamente o `event_kits.price` de antes.

### Fatias

1. **Estrutura + migração + painel.** O checkout **não muda**: continua em
   `event_kits.price`. Serve para conferir a grade migrada em produção antes
   de qualquer atleta ser cobrado por ela.
2. **Fluxo do atleta.** Kit por modalidade, tamanho do kit, lote vigente,
   categoria etária, resumo ao vivo e o retrato completo na inscrição.
3. **Menu** em accordion: Eventos › Eventos, Modalidades, Kits, Lotes,
   Categorias. Preços e o critério de idade vivem nas abas do evento.

## Plano de testes

- `PrecosLegadosTest` — evento na estrutura antiga → lote, vínculos, preços
  iguais aos dos kits, tamanhos com a exceção do "sem camiseta"; rodar duas
  vezes não duplica nada; inscrição existente intocada.
- `LoteVigenteTest` — por data, por quantidade, por ordem, nenhum, futuro.
- `CategoriaEtariaTest` — os dois critérios nas fronteiras, maior desconto em
  reais, nenhuma, sem nascimento.
- `PrecoDaInscricaoTest` — cascata, arredondamento, teto, gratuita.
- Painel (`Admin/LotCrudTest`, `Admin/AgeCategoryCrudTest`,
  `Admin/PriceGridTest`, `Admin/KitOptionsTest`) — caminho feliz, validação e
  **isolamento** de cada um.
- Fluxo do atleta (`InscricaoComPrecoDeTabelaTest`) — kit fora da modalidade
  recusado; tamanho exigido só quando o kit tem; tamanho fora do kit recusado;
  sem preço recusado; sem lote fechado; lote e categoria gravados; cascata
  com cupom; inscrição gratuita por desconto de idade + cupom.
- Os testes existentes de inscrição passam a preparar o evento com
  `PrecosLegados::migrarEvento()` — é o mesmo caminho que produção percorre.

## Critérios de aceite

- Organizador cadastra lote, categoria, vínculo kit↔modalidade, tamanhos e
  grade, tudo pelo navegador, sem ver dado de outro organizador.
- Depois da migração, cada evento em produção tem "Lote 1" e a grade mostra
  exatamente os preços dos kits de antes.
- Atleta: só vê kits da modalidade, escolhe tamanho quando o kit tem, vê o
  total com os descontos antes de confirmar, e a inscrição grava o retrato.
- Suíte inteira verde; `CHANGELOG.md`, `docs/backlog.md`,
  `docs/arquitetura.md` e a ADR 0007 atualizados.
