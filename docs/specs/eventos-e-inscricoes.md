# Eventos, modalidades, kits e inscrição

Status: Baseline (descreve o comportamento **atual**, bugs incluídos — ver `docs/backlog.md`)

## Problema

Um atleta precisa conseguir ver os eventos de um organizador, escolher uma modalidade (distância) e um kit, e se inscrever.

## Modelos envolvidos

- `Event` (`organizer_id`, `title`, `slug`, `description`, `schedule`, `registration_info`, `location`, `event_date`, `registration_deadline`, `banner_url`, `banner_ratio`, `accent_color`, `active`)
- `EventModality` (`event_id`, `name`, `distance_km`, `max_participants`, `registered_count`, `active`)
- `EventKit` (`event_id`, `name`, `description`, `price`, `stock`, `sold`, `active`)
- `Subscription` (`event_id`, `user_id`, `modality_id`, `kit_id`, `team_name`, `list_price`, `discount_amount`, `coupon_id`, `price`, `bib_number`, `status`, `confirmed_at`, `cancelled_at`) — único por `(event_id, user_id)` no banco

## Fluxos atuais

### Listar eventos do organizador atual
`GET /` → `EventsController@index`. Retorna eventos com `active = true` **e** `organizer_id` = organizador do domínio atual, ordenados por `event_date` ascendente, com `modalities` carregado.

### Ver detalhe de um evento
`GET /event/{event_id}` → `EventsController@show`. Busca por `organizer_id` do domínio atual + ID, com `modalities` e `kits` carregados. 404 se o evento não existir *ou não pertencer ao organizador atual* — este endpoint escopa por tenant corretamente.

A descrição, o cronograma e o texto do bloco "Inscrição" saem `nl2br(e(...))`: a quebra de linha digitada no
painel vale na página, e o texto continua escapado. O bloco "Cronograma" só
aparece com `schedule` preenchido; o bloco "Inscrição" fica sempre de pé (o
prazo é calculado) e é só o parágrafo livre que some — até 2026-09-21 ele era texto fixo no Blade,
igual em todo evento (ver `docs/specs/painel-admin.md`).

### Formulário de inscrição
`GET /subscribe/event/{event_id}` (autenticado) → `SubscribeController::showSubscribeForm`. **Bug (BUG-005):** busca o evento só por ID (`Event::findOrFail`), sem filtrar por organizador do domínio atual — diferente do endpoint de detalhe acima.

### Criar inscrição
`POST /subscribe/event/{event_id}` (autenticado) → `SubscribeController::subscribe`.

Comportamento atual:
1. Busca o evento por ID (mesmo problema de escopo do item anterior — **BUG-005**, ainda aberto) e recusa se não estiver com inscrições abertas.
2. Valida que `modality_id` e `kit_id` vieram no request **e** que existem em `event_modalities`/`event_kits` pertencendo a este `event_id` (`Rule::exists(...)->where('event_id', ...)`) — corrigido em 2026-07-30 (BUG-002). As colunas `subscriptions.modality_id`/`kit_id` agora são `foreignId` de verdade, com `restrictOnDelete()` (não dá pra apagar um kit/modalidade que já tem inscrição). O campo `cupom` é opcional.
3. Se já existe uma `Subscription` **ativa** (`pending` ou `paid`) do usuário pra esse evento → redireciona pra "minhas inscrições" avisando que já está inscrito. Isso vem antes do cupom, para não gastar uso à toa. Se a existente estiver **cancelada**, ela é reaproveitada no passo 5 (ver "Cancelar inscrição" abaixo).
4. Se veio cupom, `App\Services\CupomNoCheckout::localizar()` acha e valida o código **pelo evento**; recusa vira erro no campo `cupom`. `App\Services\PrecoDaInscricao` faz a conta em centavos (2026-09-20, ver `docs/specs/cupons-de-desconto.md`).
5. Numa transação: consome o uso do cupom (`Coupon::registrarUso()`, `UPDATE` condicional) e cria a `Subscription` com `status = pending`, `list_price` (preço do kit), `discount_amount`, `price` (o cobrado) e `coupon_id`. BUG-001 (preço fixo) foi corrigido em 2026-08-02 — `price` é o preço do kit, menos o desconto.
6. Valor zero (cupom de 100%) → confirma na hora por `ConfirmacaoDeInscricao` (`paid`, `confirmed_at`, e-mail) e não gera Pix. Senão, redireciona pra "minhas inscrições", onde o atleta vê o valor (e o desconto) antes de pagar.

O campo `equipe` (2026-09-22) é opcional e **texto livre**: só letras, números
e espaço, até 50 caracteres, gravado em `team_name` em CAIXA ALTA e sem espaço
sobrando (`Subscription::normalizarEquipe`). Existe `teams` desde 2026-08-29,
mas na primeira prova ninguém sabe ainda quais assessorias vão aparecer —
escolher de uma lista vazia seria pior. O nome da coluna guarda o lugar para o
vínculo de verdade.

Existe ainda `POST /subscribe/event/{event_id}/cupom` (autenticado, `throttle:20,1`) → `SubscribeController::previaDoCupom`: a prévia do formulário, que valida o código para o kit escolhido e devolve os valores em JSON sem criar nada.

Não há verificação de `registration_deadline` nem de `max_participants` da modalidade (BUG-005, ainda aberto).

### Minhas inscrições
`GET /my-subscriptions` (autenticado) → `SubscribeController::mySubscriptions`. Lista inscrições do usuário logado, filtradas pelas que pertencem a eventos do organizador do domínio atual (`whereHas('event', ...)`) — este endpoint escopa por tenant corretamente.

### Cancelar inscrição
`POST /subscription/cancel` (autenticado) → `SubscribeController::cancel`. Só permite cancelar se a inscrição estiver pendente. **Desde 2026-09-21 a linha FICA**, marcada com `status = 'cancelled'` e `cancelled_at` — antes ela era apagada, e o organizador não tinha como saber que alguém desistiu (ver `docs/specs/gestao-de-inscricoes.md`). Os `Payment` pendentes continuam sendo apagados. O uso do cupom, se houve, **não volta** para o contador (decisão do dono, 2026-09-20). Quem cancela e se inscreve de novo **reaproveita a mesma linha**, porque a unique `(event_id, user_id)` não deixa criar uma segunda.

## Bugs conhecidos nesta área

Ver `docs/backlog.md`: BUG-001 (preço fixo, aberto), BUG-005 (sem escopo de tenant/prazo/capacidade no fluxo de inscrição, aberto). BUG-002 (FK ausente em modality_id/kit_id) e BUG-003 (`canceled` vs `cancelled`) corrigidos em 2026-07-30.

## Fora de escopo hoje

- Número de peito (`bib_number`) não é gerado em lugar nenhum ainda — coluna existe, nunca é preenchida.
- Não há painel para o organizador cadastrar evento/modalidade/kit — entra via seeder/banco direto (fase 2 do backlog).
- `registered_count` em `EventModality` existe na tabela mas não é incrementado por nenhum código atual.

## Plano de testes

Cobertos em `tests/Feature/SubscribeControllerTest.php`:
- `SubscribeController@subscribe` rejeita `modality_id`/`kit_id` que não pertencem ao `event_id`.
- `SubscribeController@subscribe` cria a inscrição com `modality_id`/`kit_id` válidos.
- `SubscribeController@subscribe` rejeita segunda inscrição ativa pro mesmo evento.
- Depois de cancelar, o usuário consegue se inscrever de novo: a linha cancelada é reaproveitada, sem erro de unique constraint (`tests/Feature/CancelarInscricaoTest.php` cobre o cancelamento em detalhe).

Ainda por escrever (dependem de BUG-001/BUG-005 corrigidos):
- `EventsController@index` só retorna eventos do organizador do domínio atual.
- `SubscribeController@subscribe` grava `price` igual ao `EventKit.price` do kit escolhido, não um valor fixo.
- `SubscribeController@subscribe` rejeita inscrição após `registration_deadline`.
- `SubscribeController@subscribe` rejeita inscrição num evento de outro organizador.
