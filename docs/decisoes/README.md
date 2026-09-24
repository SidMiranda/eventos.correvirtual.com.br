# Decisões arquiteturais (ADRs)

Registro das decisões de arquitetura com consequência de longo prazo. Cada uma é um arquivo numerado sequencialmente: `000X-titulo-curto.md`.

Antes de propor uma mudança que contradiz uma ADR existente, leia a ADR — se ainda fizer sentido revisitar, crie uma **nova** ADR explicando a mudança e referenciando a antiga (não edite a antiga por fora do que for corrigir informação errada nela).

## Modelo

```markdown
# 000X — Título curto

Status: Aceita | Proposta | Substituída por 000Y

## Contexto
Qual problema/pergunta motivou essa decisão.

## Decisão
O que foi decidido.

## Alternativas consideradas
O que mais foi cogitado e por que não foi escolhido.

## Consequências
O que essa decisão implica — inclusive trade-offs aceitos de olhos abertos.
```

## Índice

| ADR | Título |
|---|---|
| [0001](0001-postgres-como-banco.md) | Postgres como banco de dados |
| [0002](0002-multi-tenancy-por-dominio.md) | Multi-tenancy por domínio |
| [0003](0003-sdd-e-fluxo-de-trabalho-com-ia.md) | SDD e fluxo de trabalho com IA |
| [0004](0004-deploy-vps-docker-git-flow.md) | Deploy em VPS via Docker + git flow |
| [0005](0005-banco-producao-hostgator-mysql.md) | Banco de produção em MySQL gerenciado na Hostgator |
| [0006](0006-painel-admin-neste-projeto.md) | Painel administrativo fica neste projeto, não no Cubo |
| [0007](0007-preco-por-modalidade-kit-e-lote.md) | Preço por modalidade × kit × lote, com desconto por idade em cascata |
