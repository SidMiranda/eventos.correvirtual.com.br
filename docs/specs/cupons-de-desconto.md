# Cupons de desconto

Status: Implementado (cadastro e gestão). Aplicação na inscrição do atleta: pendente.

## Problema

O organizador não tem nenhuma forma de dar desconto. Hoje, para fazer uma
promoção — parceria com assessoria, cortesia de patrocinador, campanha de
lançamento, "traga um amigo" — sobram duas saídas ruins: baixar o preço do kit,
que muda o valor **para todo mundo**, ou acertar por fora da plataforma e receber
o atleta já inscrito sem pagamento registrado. As duas quebram o relatório de
caixa e nenhuma é reversível.

Cupom é a ferramenta padrão de qualquer plataforma de inscrição esportiva
justamente porque resolve isso: desconto controlado, com validade, com teto de
uso, rastreável, e que não toca no preço de tabela.

## Requisitos

- [x] O organizador cria, edita, ativa/inativa e apaga cupons dos eventos dele.
- [x] Todo cupom pertence a **um** evento. Cupom de um evento nunca vale em outro.
- [x] O código tem 6 ou 7 caracteres, só A–Z e 0–9, sempre gravado em maiúsculo,
      e não se repete dentro do mesmo evento.
- [x] O desconto é percentual (1 a 100) ou valor fixo em reais (maior que zero).
- [x] O cupom tem uma quantidade total de usos e uma data de expiração.
- [x] A quantidade utilizada nunca é editável na mão.
- [x] Um cupom que já teve uso não pode ser apagado, e nem o código nem o evento
      dele podem ser trocados.
- [x] Um cupom esgotado ou vencido lê como inativo e não pode ser reativado.
- [x] Enquanto não estiver encerrado, o organizador liga e desliga o cupom à
      vontade, mesmo com usos registrados.
- [x] O incremento do contador de uso não estoura o limite sob concorrência.
- [x] Nenhum organizador enxerga, edita ou apaga cupom de outro, nem informando
      o id na URL.
- [ ] O atleta informa um cupom ao se inscrever e o valor cobrado no Pix cai.

## Fora de escopo

Desta fatia, de propósito:

- **A aplicação do cupom na inscrição.** O campo "tenho um cupom" na tela do
  atleta, a conferência do código, o abatimento em `subscriptions.price` e o
  valor que vai para o Mercado Pago ficam para a fatia seguinte. O fluxo de
  dinheiro (`SubscribeController` → `PixController` → webhook) não foi tocado
  aqui — é onde o BUG-005 ainda está aberto. A regra de consumo já existe e está
  testada (`Coupon::registrarUso()`), então a fatia seguinte é chamá-la e gravar
  o vínculo.
- **Histórico de uso.** Não há tabela ligando cupom a inscrição: hoje o cupom só
  sabe *quantas* vezes foi usado, não *por quem*. Quando a aplicação na inscrição
  entrar, `subscriptions` ganha `coupon_id` e `discount_amount`, e aí o histórico
  existe de graça.
- **Cupom do organizador valendo em vários eventos.** Um cupom para a temporada
  inteira é pedido comum, mas mudaria a chave da tabela e a regra de unicidade.
  Enquanto houver um organizador com poucos eventos, recadastrar é mais barato
  que a estrutura.
- **Cupom por modalidade ou por kit** (desconto só no 10 km, só no kit camiseta).
- **Cupom de uso único por atleta.** O limite hoje é global do cupom, não por
  pessoa: nada impede o mesmo atleta usar duas vezes, quando isso passar a ser
  possível. Depende do histórico acima para ser implementável.
- **Geração automática de códigos em lote.**

## Design

### Tabela `coupons`

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | id | |
| `event_id` | FK → events | `cascadeOnDelete`. Apagar o evento leva os cupons dele. |
| `code` | string(7) | 6–7 caracteres, A–Z e 0–9, sempre maiúsculo |
| `description` | text nullable | anotação do organizador; não aparece para o atleta |
| `discount_type` | string(10) | `percent` ou `amount` |
| `discount_value` | decimal(10,2) | 1–100 se percentual; > 0 se valor |
| `total_quantity` | unsignedInteger | quantos usos o cupom permite |
| `used_quantity` | unsignedInteger, default 0 | só o sistema escreve |
| `expires_at` | date | vale **até o fim** desse dia |
| `active` | boolean, default `true` | liga/desliga manual |
| timestamps | | |

Índice: `unique(event_id, code)` — que também serve de índice para `event_id`,
já que a coluna é o primeiro campo da chave.

**Sem `organizer_id`.** O vínculo com o organizador passa pelo evento, como em
`event_kits` e `event_modalities`. É a mesma escolha já registrada no
`DashboardController` para `subscriptions`: quem não tem organizador próprio
chega nele por `whereHas('event', …)`.

**`discount_type` é `string` e não `enum`.** Dev roda Postgres e produção roda
MySQL (ADR 0005), e `enum` vira coisa diferente em cada um — no Postgres, o
Laravel monta um `varchar` com `check`; no MySQL, um tipo nativo. Não vale a
divergência por uma coluna de dois valores que o model e a validação já garantem.

### Unicidade por evento, não global

O código é único **dentro do evento**, não no sistema inteiro. Unique global
acoplaria organizadores diferentes: o organizador A passaria a bloquear
"CORRE10" para o B, e a mensagem de erro contaria que o código existe em algum
lugar que o B não pode ver — o mesmo tipo de vazamento entre inquilinos que o
BUG-005 representa. Como a busca do cupom sempre parte do evento em que ele está
sendo usado, não existe ambiguidade a resolver.

### Estados do cupom

Não há coluna de status: o estado é deduzido, como a situação do evento já é
deduzida das datas (`Event::situacao()`).

```mermaid
stateDiagram-v2
    [*] --> Ativo
    Ativo --> Inativo: organizador desliga
    Inativo --> Ativo: organizador liga
    Ativo --> Esgotado: used_quantity = total_quantity
    Ativo --> Vencido: passou de expires_at
    Inativo --> Esgotado: (pelo contador)
    Inativo --> Vencido: (pela data)
    Esgotado --> [*]
    Vencido --> [*]
```

**Encerrado** = esgotado **ou** vencido. É um estado sem volta: o toggle fica
desabilitado na tela e o backend recusa a troca nos dois sentidos — um cupom
encerrado já lê como inativo, então "desligar" não significaria nada.

Um cupom **vale para uso** quando `active = true`, não está vencido e ainda tem
saldo. Essa é a condição que a fatia da inscrição vai consultar.

### Consumo atômico

`Coupon::registrarUso()` não lê e depois grava — faz um `UPDATE` condicional e
olha quantas linhas mudaram:

```php
static::whereKey($this->getKey())
    ->where('active', true)
    ->whereColumn('used_quantity', '<', 'total_quantity')
    ->where('expires_at', '>=', now()->toDateString())
    ->increment('used_quantity');
```

Duas requisições disputando a última vaga: quem chega depois recebe 0 linhas
afetadas e sai com `false`. Sem transação, sem lock explícito e sem estourar o
limite — a condição vive no `WHERE`, que o banco avalia e aplica na mesma
operação. É a diferença entre "sobrou 1, pode usar" (que pode estar velho quando
o `UPDATE` sai) e "decremente **se** sobrar".

### Travas depois do primeiro uso

Uma vez que o cupom foi usado, ele deixou de ser só um cadastro e virou parte do
histórico de quem pagou menos:

- **Apagar** deixa de ser possível. A saída é desativar.
- **Código e evento** ficam congelados. Trocar o código de um cupom já usado
  reescreveria o passado; trocar o evento moveria um desconto concedido para uma
  prova onde ele nunca valeu.
- Desconto, quantidade, validade, descrição e o liga/desliga continuam editáveis
  — são o futuro do cupom, não o passado.

As três travas vivem no controller, não só na tela. A tela desabilita o botão e
trava o campo porque é o comportamento honesto; o servidor recusa porque
esconder no front não é proteger.

### Rotas

Dentro do grupo `/admin` já existente (`auth` + `organizer.admin`):

```
GET     /admin/cupons                lista, filtra e busca
POST    /admin/cupons                cria
PUT     /admin/cupons/{id}           atualiza
DELETE  /admin/cupons/{id}           apaga (só sem uso)
PATCH   /admin/cupons/{id}/status    liga/desliga
```

Sem `create` e `edit`: o formulário é um modal da própria listagem, o único do
painel — os outros cinco cadastros usam página cheia. A escolha foi do dono do
projeto: cupom é registro curto e de vida curta, e sair da lista para criar um e
voltar para criar o próximo é atrito à toa.

O escopo segue a regra geral do painel: busca **já filtrando** pelo organizador
do usuário logado (via evento) e 404 se não achar — nunca busca por id solto e
confere depois.

### Tela

Uma tela só, `/admin/cupons`, com filtro por evento e busca por código. A tabela
mostra código, evento, desconto formatado (`10%` ou `R$ 25,00`), expiração,
quantidade gerada, quantidade utilizada, o toggle de situação e as ações.

O `<select>` de evento do formulário lista apenas eventos do organizador que
ainda não aconteceram — prova realizada não recebe cupom novo. Na edição, o
evento atual do cupom entra na lista mesmo se a prova já passou, senão não daria
para corrigir a descrição de um cupom antigo.

## Plano de testes

`tests/Feature/Admin/CouponCrudTest.php` — no molde de `SponsorCrudTest`, com
dois organizadores e um admin de apenas um deles:

- Criação normalizando a entrada (`"  corre10 "` vira `CORRE10`).
- Recusa: código de 5 caracteres, código com hífen, código repetido **no mesmo
  evento**. Aceita o mesmo código em **outro** evento.
- Recusa: percentual 0 e 101, valor 0, quantidade 0, data no passado.
- Recusa criar cupom em evento de outro organizador.
- Isolamento: a listagem não mostra cupom alheio; `update`, `destroy` e `status`
  de cupom alheio devolvem 404 e o registro do outro fica intacto.
- Apagar cupom sem uso funciona; com uso, é recusado e o registro continua lá.
- Cupom usado: `code` e `event_id` enviados na marra são recusados; os demais
  campos gravam normalmente.
- Toggle liga e desliga; esgotado e vencido são recusados.
- Filtro por evento e busca por código.

`tests/Unit/CouponTest.php`:

- `registrarUso()` incrementa, e devolve `false` quando a última vaga acabou —
  duas chamadas seguidas na última vaga não passam do total.
- `encerrado()` por esgotamento e por data; vencendo **hoje** ainda vale.
- `descontoSobre()` nos dois tipos, e o teto no valor da inscrição (cupom de
  R$ 50 num kit de R$ 30 abate R$ 30, não gera cobrança negativa).
- `descontoFormatado()`.

## Critérios de aceite

- O organizador cria, edita, liga/desliga e apaga cupons pelo navegador, com o
  visual do painel.
- Cupom usado não é apagado nem tem código/evento trocados, mesmo com a
  requisição montada na mão.
- Cupom esgotado ou vencido não reativa.
- Nenhum organizador alcança cupom de outro.
- A suíte passa inteira.
- `CHANGELOG.md` e `docs/backlog.md` atualizados.
