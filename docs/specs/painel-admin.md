# Painel administrativo do organizador

Status: Fatias 1 e 2 implementadas; escolha de equipe na inscrição pendente

## Problema

Hoje **não existe nenhuma tela administrativa**. Evento, modalidade e kit só entram no sistema por seeder ou escrevendo direto no banco — o organizador depende do desenvolvedor pra qualquer coisa, até corrigir um preço errado. É a maior lacuna funcional do produto: o sistema sabe *vender* uma inscrição, mas não sabe deixar ninguém *cadastrar* o que está sendo vendido.

Decisão do dono do projeto em 2026-08-29: o painel é construído **neste projeto**, no servidor onde ele já roda — não migra pro Cubo. A ideia de levar o cadastro pra lá foi considerada e descartada (ver `docs/decisoes/0006-painel-admin-neste-projeto.md`).

## O que já está implementado (2026-08-29)

**Fatia 1:** entrada do painel (`/admin`, papel `organizer_admin`, `admin:criar`), layout do SB Admin Pro e CRUD de eventos. `admin.correvirtual.com.br` no ar com certificado próprio.

**Fatia 2:** CRUD de modalidades e kits aninhados no evento (`/admin/eventos/{id}/modalidades` e `.../kits`), CRUD de equipes por organizador (`/admin/equipes`), upload de banner e card direto para o R2, e situação do evento deduzida das datas.

**Ainda não:** a escolha de equipe na tela de inscrição do atleta. O cadastro existe e o filtro está pronto e testado (`Team::escolhivelPeloAtleta()`), mas a coluna `subscriptions.team_id` não foi criada e a tela do atleta não foi tocada — foi o combinado desta rodada.

## Requisitos

- [ ] Um usuário com papel `organizer_admin` consegue entrar no painel e só enxerga dados do organizador dele.
- [ ] Um usuário com papel `athlete` (ou deslogado) **não** consegue acessar nenhuma rota do painel.
- [ ] O organizador cadastra, edita, ativa/desativa e lista seus **eventos**.
- [ ] O organizador cadastra as **modalidades** (as distâncias — 5km, 10km, Caminhada 3km) de cada evento seu.
- [ ] O organizador cadastra os **kits** (nome, descrição, preço, estoque) de cada evento seu.
- [ ] O organizador cadastra as **equipes** que participam dos eventos dele, marcando cada uma como aberta ou fechada.
- [ ] O atleta, ao se inscrever, pode escolher uma equipe numa lista que mostra **apenas as equipes abertas** do organizador daquele site.
- [ ] Nenhuma tela do painel permite ler, editar ou apagar registro de outro organizador, nem informando o ID na URL.

## Fora de escopo

Fica explicitamente para depois, pra esta fatia não crescer sem controle:

- **Catálogo reutilizável de kits/modalidades entre eventos.** Decisão do dono (2026-08-29): kit e modalidade continuam pertencendo ao **evento**, como já é hoje. Cadastrar um evento novo significa cadastrar os kits dele de novo.
- **Categoria de premiação** (faixa etária/sexo). Decisão do dono: a distância se chama **modalidade** — é o que o banco chama de `EventModality`, e é esse o nome usado na tela desde 2026-08-29 (antes a tela dizia "categoria", corrigido a pedido dele). Não existe entidade separada de faixa etária.
- **Inscrição em lote pelo capitão da equipe.** A equipe aqui é só um vínculo do atleta; não existe capitão inscrevendo o grupo nem pagamento único de vários atletas.
- Relatório/exportação de inscritos, geração de número de peito, check-in, lotes de preço por data. Continuam no `docs/backlog.md`.
- Papel `super_admin` e tela de cadastro de organizador — o organizador ainda é criado na mão (ver `docs/runbook.md`).
- Upload de imagem de banner pelo painel; por ora `banner_url` continua sendo um texto.

## Design

### Entrada e autorização

O painel vive em `/admin`, dentro da mesma aplicação Laravel. Duas travas, ambas obrigatórias:

1. Middleware `auth` — precisa estar logado.
2. Middleware `EnsureOrganizerAdmin` — o usuário precisa ter `role = 'organizer_admin'` **e** `organizer_id` preenchido. Sem os dois, responde 403.

O `organizer_id` do **usuário logado** é a fonte da verdade do escopo dentro do painel — não o domínio da requisição. Isso é uma diferença deliberada em relação ao site público, onde quem manda é o domínio (`IdentifyOrganizerByDomain`, ver ADR 0002): o painel precisa funcionar tanto em `admin.correvirtual.com.br` quanto em `/admin` do domínio do organizador, e em ambos o organizador correto é o do usuário.

Para isso, `IdentifyOrganizerByDomain` passa a **não abortar com 404** quando a rota é do painel — sem essa exceção, `admin.correvirtual.com.br` cairia no 404 de "organizador não encontrado", já que esse domínio não pertence a nenhum organizador.

O primeiro administrador é criado por linha de comando (`php artisan admin:criar`), porque não existe tela de cadastro de admin nem papel `super_admin` implementado.

### Escopo por organizador

Toda consulta do painel parte do organizador do usuário logado. Duas regras que valem para todos os controllers:

- **Listagem:** filtra por `organizer_id` do usuário.
- **Registro específico** (editar, atualizar, apagar): busca **já filtrando** pelo organizador, e devolve 404 se não achar — nunca busca por ID solto e checa depois. Para modalidade e kit, que não têm `organizer_id` próprio, o filtro passa pelo evento: `whereHas('event', fn ($q) => $q->where('organizer_id', $id))`.

O 404 (e não 403) é proposital: um organizador não deve nem descobrir que o registro de outro existe.

### Tabelas

Nenhuma tabela existente é alterada, exceto pela coluna nova em `subscriptions`.

**`teams`** (nova) — a equipe pertence ao **organizador**, não ao evento: a mesma assessoria participa de vários eventos ao longo do ano.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | id | |
| `organizer_id` | FK → organizers | `cascadeOnDelete` |
| `name` | string | |
| `slug` | string | único **por organizador**, não global |
| `description` | text nullable | |
| `is_public` | boolean, default `true` | `true` = aberta (aparece pro atleta escolher); `false` = fechada |
| `active` | boolean, default `true` | |
| timestamps | | |

**`sponsors`** (nova, 2026-08-30) — como a equipe, o patrocinador pertence ao **organizador**: o mesmo apoiador cobre várias provas ao longo do ano, e amarrá-lo a um evento obrigaria a recadastrar a cada prova.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | id | |
| `organizer_id` | FK → organizers | `cascadeOnDelete` |
| `name` | string | |
| `site_url` | string nullable | precisa de esquema (`https://`) — sem ele o link vira relativo e leva para dentro do painel |
| `description` | text nullable | observação interna; não aparece no site |
| `has_logo` | boolean, default `false` | a marca de que existe logo no bucket |
| `position` | integer, default `0` | ordem de exibição; menor primeiro, nome desempata |
| `active` | boolean, default `true` | tira do site sem apagar o cadastro |
| timestamps | | |

**Sem `slug`**, diferente de `teams`: patrocinador não tem página nem endereço próprio neste sistema — seria coluna sem uso.

**`subscriptions.team_id`** (nova coluna) — `foreignId` nullable, `nullOnDelete()`: apagar uma equipe não pode apagar inscrição de ninguém, só desvincula.

### "Aberta" e "fechada"

- **Aberta** (`is_public = true`): aparece na lista que o atleta vê ao se inscrever, e ele pode se vincular sozinho.
- **Fechada** (`is_public = false`): existe no sistema e pode ter atletas vinculados, mas **não aparece** na lista do atleta. Serve pra equipe fechada/convidada, em que o vínculo é decidido pelo organizador.

O filtro do que o atleta vê é sempre `organizer_id = atual AND is_public = true AND active = true`. Uma equipe fechada nunca é aceita vinda do formulário do atleta, mesmo que ele mande o ID na mão — a validação confere as três condições no banco, não confia no que veio do navegador.

### Rotas

Todas sob `/admin`, com os dois middlewares:

```
GET    /admin                         painel (contadores)
resource /admin/eventos               eventos
resource /admin/eventos/{evento}/modalidades
resource /admin/eventos/{evento}/kits
resource /admin/equipes               equipes
resource /admin/patrocinadores        patrocinadores
resource /admin/cupons                cupons (sem create/edit: o form e um modal)
PATCH    /admin/cupons/{id}/status    liga/desliga o cupom
```

Modalidades e kits são aninhados em evento de propósito: eles não existem fora de um evento, e a rota aninhada torna impossível cadastrar um kit sem dizer de qual evento é.

Cupom também pertence a um evento, mas a rota **não** é aninhada: quem abre essa tela quer ver os descontos que estão de pé agora, em todos os eventos, e não navegar prova por prova. O evento entra como campo do formulário. Ver `docs/specs/cupons-de-desconto.md`.

### Visual

Layout próprio (`layouts/admin.blade.php`) a partir do SB Admin Pro em `TEMPLATES/Painel-Admin/`, com a paleta já usada no projeto (`--cv-navy` `#0d1b2a`, `--cv-blue` `#1a71b2`). O painel não reaproveita `layouts/app.blade.php` nem `app-v2` — são públicos e têm menu de atleta.

## Plano de testes

Automatizados (`tests/Feature/Admin/`), obrigatórios antes de considerar pronto:

**Acesso**
- Deslogado em rota do painel → redireciona pro login.
- Logado como `athlete` → 403.
- Logado como `organizer_admin` sem `organizer_id` → 403.
- Logado como `organizer_admin` com organizador → 200.

**Isolamento (o mais importante — é o BUG-005 aberto hoje)**
- Admin do organizador A não vê eventos/equipes do B na listagem.
- Admin de A abrindo a edição de um evento do B → 404.
- Admin de A tentando atualizar (`PUT`) um evento do B → 404, e o registro do B fica intacto.
- O mesmo para modalidade e kit, que herdam o escopo pelo evento.
- Admin de A não consegue criar modalidade/kit dentro de um evento do B.

**Cadastros**
- Criar, editar e listar evento, modalidade, kit e equipe pelo caminho feliz.
- Validação recusa campos obrigatórios vazios e preço negativo.

**Equipe no lado do atleta**
- A lista de equipes na inscrição mostra só as abertas e ativas do organizador atual.
- Inscrição enviando o ID de uma equipe **fechada** é recusada.
- Inscrição enviando o ID de uma equipe **de outro organizador** é recusada.
- Inscrição sem equipe continua funcionando (o campo é opcional).

Manual, no Playwright, contra o ambiente local: entrar no painel, cadastrar um evento com modalidade e kit, criar uma equipe aberta e outra fechada, e conferir na tela pública de inscrição que só a aberta aparece.

## Critérios de aceite

- Os quatro cadastros funcionam de ponta a ponta pelo navegador, com o visual do template.
- A suíte passa inteira, incluindo os testes de isolamento acima.
- O atleta consegue escolher equipe aberta ao se inscrever, e não consegue se vincular a uma fechada nem à de outro organizador.
- `CHANGELOG.md`, `docs/backlog.md` e `docs/arquitetura.md` atualizados.
