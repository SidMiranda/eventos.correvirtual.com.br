# Gestão de inscrições no painel

Status: Implementado (2026-09-21)

## Problema

O organizador não tinha como ver quem se inscreveu. O painel contava inscrições
no dashboard e parava aí: sem lista, sem filtro, sem ficha de atleta e sem
relatório. Na véspera do lançamento, era a maior lacuna que sobrava — o sistema
sabia vender a inscrição, mas não sabia mostrar quem comprou.

Pedido do dono (2026-09-21), nas palavras dele: tela de inscrições com filtro
por evento, relatório em PDF que **abre em aba nova** em vez de baixar e só sai
com evento escolhido, filtros de pagas / aguardando pagamento / canceladas /
com desconto, e a possibilidade de ir ao cadastro do atleta.

## Requisitos

- [x] Lista de inscrições dos eventos do organizador, com filtro por evento,
      por situação, por "só com desconto" e busca por nome, e-mail ou CPF.
- [x] Totais do recorte atual: inscrições, pagas, aguardando, canceladas,
      arrecadado e descontos concedidos.
- [x] Relatório em PDF do evento escolhido, abrindo em aba nova.
- [x] Lista e ficha de atletas, com as inscrições dele nos eventos do organizador.
- [x] Inscrição cancelada passa a existir (antes a linha era apagada).
- [x] Nenhum organizador vê inscrição, atleta ou relatório de outro.

## Fora de escopo

- **Editar o cadastro do atleta.** Decisão do dono: a ficha é só consulta.
  Mexer em e-mail ou senha de outra pessoa é delicado e não foi pedido.
- **Exportar em CSV/Excel.** O pedido foi PDF. Continua no `docs/backlog.md`.
- **Histórico de cada tentativa de inscrição.** A unique `(event_id, user_id)`
  guarda uma linha por par, então quem cancela e se inscreve de novo reaproveita
  a mesma — o cancelamento anterior deixa de aparecer. Guardar cada tentativa
  exigiria derrubar a unique e repensar "já inscrito".
- **Marcar inscrição como paga pelo painel** (pagamento fora do sistema, em
  dinheiro). Não foi pedido, e mexeria no fluxo de dinheiro.
- **Número de peito e check-in.** Continuam no backlog.

## Design

### A inscrição cancelada passa a existir

Até 2026-09-21, `SubscribeController::cancel()` **apagava** a linha. A decisão
vinha do BUG-003 (2026-07-30), que resolveu a inconsistência `canceled` ×
`cancelled` escolhendo o delete. O efeito colateral só apareceu agora: sem
linha, não há o que listar — o organizador não tinha como saber que alguém
desistiu, a inscrição simplesmente sumia da base.

Agora cancelar grava `status = 'cancelled'` e `cancelled_at`. A cobrança
pendente continua sendo apagada: ninguém paga um Pix de inscrição cancelada, e
deixá-la atrapalharia a conciliação.

**A grafia importa.** O enum do banco usa **dois L** (`cancelled`), e as duas
views de "minhas inscrições" comparavam com **um só** — comparação que nunca
batia, escondida pelo fato de que o caso não existia. Passando a existir, ela
cairia no `@else` e mostraria "Cancelled" cru para o atleta. Corrigido nas duas.

**Reinscrição.** A unique `(event_id, user_id)` impede uma segunda linha, então
`subscribe()` distingue: inscrição **ativa** (pendente ou paga) barra com o
modal de "já inscrito", como sempre; inscrição **cancelada** é reaproveitada —
volta a `pending` com a modalidade, o kit, o cupom e os valores da nova
tentativa. O uso do cupom da tentativa anterior não volta (decisão de
2026-09-20) e a nova consome um uso próprio.

### `subscriptions.cancelled_at`

Única coluna nova. O `enum` já aceitava `cancelled` desde a migration original.

### O filtro, em um lugar só (`App\Support\FiltroDeInscricoes`)

A tela e o PDF usam o **mesmo** objeto de filtro. Se a tradução dos filtros
vivesse no controller da tela, o relatório teria uma segunda cópia — e a
primeira diferença entre as duas seria um número errado num papel entregue a
alguém.

| Filtro | Valores |
|---|---|
| `evento` | id de um evento do organizador |
| `situacao` | `pagas`, `pendentes`, `canceladas` |
| `desconto` | `1` — só as que usaram cupom |
| `busca` | nome, e-mail ou CPF do atleta |

Valor desconhecido em `situacao` é tratado como "todas": filtro é conveniência,
não deve virar erro na cara de quem usa. O CPF é comparado só pelos dígitos —
no banco está sem pontuação e a pessoa digita com.

### A tela (`/admin/inscricoes`)

Quatro cartões com as contagens e dois com o dinheiro, todos obedecendo ao
filtro atual: o que se lê no topo é sempre o que está na tabela abaixo.
**Arrecadado** e **descontos concedidos** contam só as **pagas** — inscrição
pendente ainda não é dinheiro. Os dois saem de `subscriptions` (`price`,
`discount_amount`), que guardam o que foi cobrado de verdade; nada é
reconstruído a partir do kit (que muda de preço) ou do cupom (que é editável).

Isolamento pelo padrão do painel: `whereHas('event', ...)` pelo organizador do
usuário logado. `subscriptions` não tem `organizer_id` — o vínculo é o evento.

### O relatório (`/admin/inscricoes/pdf`)

`barryvdh/laravel-dompdf`, A4, servido com `Content-Disposition: inline` — é
esse cabeçalho que faz o navegador **exibir** em vez de baixar; o
`target="_blank"` do link cuida da aba nova.

**Exige evento.** Uma lista com provas misturadas não serve no papel: é por
evento que se confere largada, kit e lote. Sem evento, volta para a tela com a
explicação, e o botão na tela fica desabilitado dizendo por quê.

A folha do dompdf não entende flexbox, grid nem variável CSS — por isso a view
do relatório é tabela e estilo inline simples, separada das views do painel.
A fonte é DejaVu Sans, que o dompdf embute: é o que garante os acentos, ao
custo de ~850 KB por arquivo.

Evento sem inscrição ainda gera o PDF: papel com o cabeçalho e a lista vazia é
resposta melhor que um erro — o organizador pediu a lista, e a lista está vazia.

### Atletas (`/admin/atletas`)

"Atleta do organizador" não é uma relação que exista no banco: a conta é da
plataforma, e `users.organizer_id` diz outra coisa (de qual organizador a
pessoa é administradora — e nem chega a ser preenchido no cadastro, BUG-006).
O que liga um atleta a este painel é **ter ao menos uma inscrição num evento
dele**. É esse o filtro de tudo aqui, e por isso atleta sem inscrição em evento
meu dá 404 — o mesmo princípio do resto do painel.

A ficha mostra o cadastro (só leitura) e as inscrições **nos meus eventos**; as
de outro organizador não aparecem.

## Plano de testes

`tests/Feature/Admin/SubscriptionListTest.php` — cada filtro isolado e
combinados; busca por nome, e-mail e CPF (com e sem pontuação); situação
desconhecida na URL mostra todas; os totais acompanham o filtro; a paginação
preserva o filtro; inscrição de outro organizador não aparece; deslogado e
atleta barrados.

`tests/Feature/Admin/SubscriptionPdfTest.php` — sem evento volta com aviso; com
evento devolve `application/pdf` com `inline` e conteúdo começando em `%PDF`;
evento de outro organizador dá 404; evento sem inscrição ainda gera; deslogado
e atleta barrados.

`tests/Feature/Admin/AthleteTest.php` — lista só quem se inscreveu em evento
meu (e conta só essas inscrições); busca por nome, e-mail e CPF; a ficha não
mostra inscrição de evento alheio; atleta sem vínculo dá 404.

`tests/Feature/CancelarInscricaoTest.php` — cancelar marca em vez de apagar e
preenche `cancelled_at`; apaga a cobrança pendente; inscrição paga não cancela;
um atleta não cancela a inscrição de outro; reinscrever reaproveita a linha e
consome um uso novo do cupom; inscrição ativa continua barrando; a tela do
atleta mostra "Cancelada" (e não "Cancelled") sem o botão de pagar.

## Critérios de aceite

- O organizador filtra as inscrições por evento, situação, desconto e busca, e
  os totais no topo batem com a lista.
- O relatório abre em aba nova, com os mesmos filtros da tela, e só com evento.
- A ficha do atleta mostra o cadastro e as inscrições nos eventos dele.
- Cancelar deixa a inscrição registrada como cancelada, e reinscrever funciona.
- Nenhum organizador alcança dado de outro.
- A suíte passa inteira; `CHANGELOG.md` e `docs/backlog.md` atualizados.
