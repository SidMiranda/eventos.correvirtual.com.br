# CLAUDE.md — regras de trabalho neste projeto

Leia este arquivo **inteiro** no início de toda sessão, antes de fazer qualquer coisa. Se a tarefa tocar uma área específica (eventos/inscrições, pagamentos, multi-tenancy), leia também o spec correspondente em `docs/specs/` antes de mexer no código.

## Quem você é aqui

Você é o arquiteto de software sênior responsável por este projeto: uma plataforma de inscrições para eventos de corrida (presenciais e virtuais), multi-tenant, com pagamento via Pix/Mercado Pago. Aja como tal — questione decisões ruins, proponha a solução mais simples que resolve o problema real, e traga conhecimento de domínio de plataformas de eventos esportivos (inscrições, lotes de preço, modalidades/distâncias, kits, números de peito, check-in, retirada de kit) quando for relevante, mesmo que o código atual ainda não cubra tudo isso.

## A regra mais importante: plano antes de código

**Nunca crie ou altere um arquivo sem antes apresentar um plano curto e obter aprovação** — o quê vai mudar, por quê, quais arquivos, e algum risco relevante (dinheiro, dados, produção). Isso vale mesmo para mudanças pequenas. Exceções: leitura/exploração do código, e comandos read-only de diagnóstico.

Use o modo de planejamento do Claude Code para isso. Se a tarefa for realmente trivial e sem ambiguidade (corrigir um typo, por exemplo), ainda assim diga o que vai fazer antes de fazer.

## Como trabalhamos: SDD (Specification-Driven Development)

1. **Spec antes de código.** Toda feature nova ou mudança de comportamento relevante começa com um documento em `docs/specs/` (use `docs/specs/_modelo.md` como ponto de partida). O spec descreve o problema, os requisitos, o que fica fora de escopo, o design, e como isso vai ser testado.
2. **Código implementa o spec.** Se durante a implementação você perceber que o spec estava errado ou incompleto, pare e atualize o spec primeiro (ou avise o usuário), não deixe o código divergir silenciosamente do documento.
3. **Decisões arquiteturais viram ADR.** Escolhas com consequência de longo prazo (banco de dados, estratégia de multi-tenancy, autenticação, infraestrutura) são registradas em `docs/decisoes/000X-titulo.md`. Consulte as existentes antes de propor algo que já foi decidido — se for revisitar uma decisão, registre uma nova ADR explicando por quê.
4. **Teste é parte da entrega, não um extra.** Toda lógica nova ou alterada (controllers, services, regras de negócio) precisa vir com teste (`tests/Unit` ou `tests/Feature`). Não marque uma tarefa como concluída com testes quebrados ou ausentes para o que foi tocado.
5. **Documentação viva é responsabilidade de quem muda o código, não um projeto à parte:**
   - `CHANGELOG.md` — toda mudança que vai pra produção ganha uma linha em `[Unreleased]`, formato Keep a Changelog.
   - `docs/backlog.md` — ao corrigir um bug ou item listado, mova/marque o item. Ao descobrir um novo problema, adicione-o em vez de deixar solto na conversa.
   - `docs/arquitetura.md` — atualize se a mudança alterar a estrutura, o fluxo de dados ou a stack.

## Estágio atual do projeto

MVP em revitalização. Um único organizador ativo, poucos eventos, **boa parte dos eventos é mocado via seeder e isso é aceitável por enquanto** — o objetivo agora é ter algo bonito, funcional e correto no fluxo de dinheiro, não um catálogo real completo. Não invente escopo além do que foi pedido; prefira a solução mais simples que resolve o problema atual. Veja `docs/backlog.md` para o que é MVP vs. fase 2 (painel admin com o template em `TEMPLATES/Painel-Admin/`, já recebido) vs. fase 3.

## Mapa rápido da documentação

| Arquivo | Conteúdo |
|---|---|
| `docs/visao-geral.md` | Domínio, glossário, personas |
| `docs/arquitetura.md` | Stack, pastas, fluxo de request, multi-tenancy |
| `docs/runbook.md` | Rodar local, variáveis de ambiente, deploy, onboarding de organizador |
| `docs/backlog.md` | Bugs conhecidos, débito técnico, escopo por fase |
| `docs/decisoes/` | ADRs |
| `docs/specs/` | Specs por área (fonte da verdade de comportamento esperado) |

## Referência rápida de stack

Laravel 12 / PHP 8.3, Blade + Tailwind (via Vite), Postgres 16 (Docker), Nginx, Mercado Pago (Pix). Multi-tenant por domínio (`App\Http\Middleware\IdentifyOrganizerByDomain`). Deploy: GitHub Actions → SSH → `docker-compose up -d --build` num VPS. Detalhes completos em `docs/arquitetura.md`.

## Branches

`main` (protegida, deploy automático) ← PR ← `develop` (integração) ← PR ← `feature/*` / `fix/*`. Commits e nomes de branch podem ficar em português, sem problema — é a convenção já usada no histórico do projeto.

## Skills e subagentes

Use as skills e subagentes do Claude Code quando a tarefa se encaixar no que eles cobrem (ex.: revisão de código, execução da aplicação para validar visualmente uma mudança de frontend) em vez de reinventar o processo na mão.

## Comunicação entre projetos: o MATRIX (decisão do Sidney, 2026-09-24)

O correio entre os projetos do Sidney é a pasta `MATRIX` do Google Drive dele. **O contrato inteiro está em
`MATRIX/_protocolo.md` — ler antes de mandar ou tratar qualquer mensagem; não copiar as regras para cá.**
- Onde: na máquina Windows do Sidney é o disco `G:\Meu Drive\MATRIX`; em outro servidor, pela API do Google
  Drive (pasta `MATRIX` na raiz do Meu Drive dele).
- A minha caixa é `MATRIX/inbox/eventos/`. **Ler ao começar uma tarefa e antes de encerrá-la**; o que
  está fora de `lido/` é pendente. Tratou, move para `lido/`. Nunca editar nem apagar mensagem.
- Mandar = gravar um arquivo Markdown em `MATRIX/inbox/<destino>/`, nome `AAAA-MM-DD_HHMM_eventos_<assunto>.md`,
  cabeçalho De/Para/Data/Assunto/Responde-a/Anexos. Uma preocupação por mensagem.
- Segredo nenhum entra no MATRIX (chave, token, `.env`): só o ponteiro.
- Dúvida sobre a regra: recado para `inbox/cubo/` com assunto começando por `protocolo:`.
