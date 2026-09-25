# Área do atleta — "Minha conta"

Status: Em implementação — fatia 1 (inscrições) e fatia 2 (perfil) em 2026-09-25

## Problema

Depois de logado, o atleta só tem "Minhas inscrições": uma lista de cards
grandes, com o cartaz inteiro de cada evento, sem separar o que já passou do
que vem pela frente — e sem como corrigir nada. Errou o tamanho da camiseta ou
a equipe, ou quer marcar que é PCD: só pedindo ao organizador.

A maioria compra e edita pelo celular (dono, 2026-09-25): a tela precisa ser
pensada para uma mão e um polegar primeiro.

## Requisitos

**Inscrições** (fatia 1)
- [x] Duas seções: **Próximas** (evento ainda não aconteceu) e **Realizadas**.
- [x] Cada inscrição numa linha compacta: miniatura do evento (não o cartaz
      grande), nome, data, modalidade/kit, valor e situação.
- [x] Ações com área de toque de celular: Pagar e Cancelar (pendente),
      Editar (fatia 3), Ver evento. Realizadas não têm ações de mudança.
- [x] `/minha-conta` e o antigo `/my-subscriptions` mostram a mesma tela — os
      e-mails já enviados e os redirecionamentos do pagamento continuam valendo.

**Perfil** (fatia 2)
- [x] O atleta altera: **nome, telefone, cidade (da lista) e PCD**.
- [x] **Não altera** (decisão do dono, 2026-09-25): data de nascimento, sexo,
      CPF, CPF do responsável e e-mail. Aparecem como leitura; mandar esses
      campos no formulário não muda nada.
- [x] Link para "Alterar senha", que já existe.
- [x] O campo de cidade com busca virou um componente (`<x-campo-cidade>` +
      `public/js/campo-cidade.js`), o mesmo no cadastro e no perfil.

**Editar inscrição** (fatia 3)
- [ ] O atleta altera **equipe** e **tamanho da camiseta** (só entre os
      tamanhos do kit dele).
- [ ] Até o prazo **"Alterações até"** do evento (campo novo). Vazio = o
      encerramento das inscrições. Depois disso, e em evento realizado ou
      inscrição cancelada, os campos aparecem travados com o motivo.
- [ ] Só a própria inscrição, só do organizador do site; a de outra pessoa dá 404.

## Fora de escopo

- **Estoque por tamanho de camiseta.** A regra "pode trocar?" mora numa classe
  só (`App\Support\AlteracaoDeInscricao`) para o estoque entrar ali depois.
- Troca de e-mail (exige confirmar o endereço novo).
- Troca de modalidade ou kit (muda o preço — vira acerto de dinheiro).
- Edição pelo organizador (item separado, no painel).

## Design

### Rotas

| Rota | Nome | O quê |
|---|---|---|
| `GET /minha-conta` e `GET /my-subscriptions` | `conta.inscricoes`, `subscriptions.my` | a lista |
| `GET/PUT /minha-conta/perfil` | `conta.perfil` | fatia 2 |
| `GET/PUT /minha-conta/inscricoes/{id}` | `conta.inscricao` | fatia 3 |

Tudo com `auth`. As duas primeiras usam o mesmo controller; nenhuma redireciona
para a outra de propósito: o aviso de "inscrição feita" vive no flash da sessão
e um redirecionamento a mais o consumiria.

### Peças

- `App\Http\Controllers\Conta\InscricoesDoAtletaController` — lista (fatia 1)
  e edição (fatia 3).
- `App\Http\Controllers\Conta\PerfilDoAtletaController` + `PerfilDoAtletaRequest`.
- `App\Support\InscricoesDoAtleta` — busca e separa em próximas/realizadas;
  a tela não decide nada.
- `App\Support\AlteracaoDeInscricao` — pode alterar? por quê não?
- Views em `resources/views/conta/`, CSS em `public/css/minha-conta.css`,
  mobile first (a regra base é o celular; `min-width` amplia para o desktop).

### Banco (fatia 3)

`events.changes_deadline` (datetime, nullable) — "Alterações até", no cadastro
do evento.

## Plano de testes

- `MinhaContaTest` — deslogado vai ao login; só as inscrições do atleta e do
  organizador do site; próximas e realizadas separadas; realizada sem Pagar/
  Cancelar; os dois endereços respondem; miniatura presente.
- `PerfilDoAtletaTest` (fatia 2), `EditarInscricaoTest` e
  `AlteracaoDeInscricaoTest` (fatia 3).
- Os testes existentes que abrem `/my-subscriptions` continuam passando sem
  mudança.
