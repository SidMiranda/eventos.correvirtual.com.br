# Runbook

## Rodar localmente

Pré-requisito: Docker Desktop rodando.

```bash
cp .env.example .env               # credenciais do container Postgres
cp src/.env.example src/.env       # config do Laravel (já vem casada com o .env acima)

docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
docker exec corre_app composer install
docker exec corre_app php artisan key:generate
docker exec corre_app php artisan storage:link

# storage/framework/* e storage/logs não existem no checkout (dirs vazios não vão pro
# git) — sem isso a aplicação responde 500 na primeira subida:
docker exec -u root corre_app sh -c "mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/app/temp storage/logs bootstrap/cache && touch storage/logs/laravel.log && chmod -R 777 storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache"

docker exec corre_app php artisan migrate:fresh --seed
```

Acesse `http://localhost`. Em ambiente `local`, o middleware `IdentifyOrganizerByDomain` resolve automaticamente o **primeiro** organizador do banco para `localhost`/`127.0.0.1`/IPs de rede local — não precisa configurar domínio.

**Por que `-f docker-compose.yml -f docker-compose.local.yml`:** a config de produção do nginx (`docker/nginx/default.conf`) exige certificados TLS reais do Let's Encrypt e nunca sobe numa máquina local sem eles. `docker-compose.local.yml` troca só o `nginx` para servir HTTP puro (`docker/nginx/local.conf`) — é um overlay 100% opcional que a produção nunca lê (o deploy roda `docker-compose up` sem `-f` nenhum).

Scripts `reset-dev.sh` (Linux/WSL) e `reset-dev.bat` (Windows) automatizam todo esse processo do zero (derrubam containers, sobem de novo, reinstalam dependências, ajustam permissões, recriam o banco) — **use-os no dia a dia em vez dos comandos manuais acima**, que existem aqui só pra documentar o que cada script faz por baixo dos panos.

### Variáveis de ambiente

Desde o ADR 0005, o banco (dev e prod) é MySQL gerenciado na Hostgator — não há mais container de banco local, então `.env` (raiz) só importa se for usar o Cloudflare Tunnel (`CLOUDFLARE_TUNNEL_TOKEN`, opcional). O que de fato configura a conexão é `src/.env`:

`DB_CONNECTION=mysql`, `DB_HOST=srv238.prodns.com.br`, `DB_PORT=3306`, `DB_DATABASE=webcit29_eventos_dev` (local) `/webcit29_eventos_prod` (produção, só no secret `APP_ENV`), `DB_USERNAME`, `DB_PASSWORD`, `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_WEBHOOK_SECRET`, `APP_URL`.

**Acesso ao banco remoto**: a Hostgator só libera conexão de IPs cadastrados em cPanel → Remote MySQL. Se der "Access denied" mesmo com usuário/senha corretos, é isso — falta liberar o IP de quem está conectando (sua máquina local, ou a VPS de produção).

### Expor o ambiente local na internet (Cloudflare Tunnel)

Pra testar em outro dispositivo, mandar link de revisão pro organizador, etc. — sem isso ser o deploy de produção. Setup (uma vez, no [painel Zero Trust da Cloudflare](https://one.dash.cloudflare.com/) → Networks → Tunnels): criar um tunnel, tipo "Cloudflared", copiar o token, e configurar um Public Hostname apontando pra `http://nginx:80` (nome do serviço no docker-compose — não `localhost`, o cloudflared roda dentro da mesma rede Docker). Cole o token em `CLOUDFLARE_TUNNEL_TOKEN` no `.env` da raiz, depois:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml -f docker-compose.tunnel.yml up -d
```

`docker-compose.tunnel.yml` é 100% opt-in — só sobe se você incluir esse `-f` explicitamente.

Pra testar o Pix de ponta a ponta com esse tunnel (webhook do Mercado Pago batendo no seu ambiente local), configure a URL pública do tunnel como "URL de notificação" no [painel do Mercado Pago](https://www.mercadopago.com.br/developers/panel) e copie o secret que ele gera pra `MERCADOPAGO_WEBHOOK_SECRET` em `src/.env`. Sem esse secret configurado, `/api/webhooks/mercadopago` rejeita toda notificação com `401` (falha fechada — ver `docs/specs/pagamentos-pix.md`).

### Verificação visual (Playwright MCP)

Pra tirar screenshot/navegar na aplicação de verdade (não só `curl`), o projeto usa o servidor MCP do Playwright, registrado em `.mcp.json` (raiz, versionado). Exige Node.js instalado no host — se `claude mcp list` não mostrar `playwright` conectado, rode `/mcp` numa sessão do Claude Code pra aprovar/depurar.

## Deploy

`main` tem deploy automático via `.github/workflows/deploy.yml`: a cada push, o workflow conecta por SSH no VPS, escreve `src/.env` a partir do secret `APP_ENV`, e roda `docker-compose up -d --build` + `migrate --force` + `optimize`.

**Banco de produção**: MySQL gerenciado na Hostgator (`webcit29_eventos_prod`), não um container local — ver `docs/decisoes/0005-banco-producao-hostgator-mysql.md`. O `docker-compose.yml` não sobe mais nenhum serviço de banco; o secret `APP_ENV` precisa ter `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT=3306`, `DB_DATABASE=webcit29_eventos_prod`, `DB_USERNAME`, `DB_PASSWORD` apontando pra Hostgator. **Pré-requisito**: o IP da VPS de produção precisa estar liberado no Remote MySQL da Hostgator (cPanel → Remote MySQL) — sem isso a conexão cai com "Access denied".

**Primeiro deploy**: depois do primeiro deploy bem-sucedido, popule o banco manualmente uma vez (o deploy só roda `migrate`, não `seed`, de propósito — pra não duplicar dados em deploys seguintes):

```bash
ssh <usuario>@<host>
docker exec corre_app php artisan db:seed --force
```

**Status atual**: `https://eventos.correvirtual.com.br` está no ar (VPS `143.95.218.62`, Hostgator) desde 2026-08-02. Certificado emitido via `certbot certonly --webroot -w src/public -d eventos.correvirtual.com.br` diretamente no host (não em container) — o container `nginx` só consome os certs que já existem em `/etc/letsencrypt`, montados read-only. Renovação automática já agendada pelo certbot (`systemctl list-timers | grep certbot`).

## Cobrança com split (Mercado Pago, ADR 0008)

**Configurar a aplicação da plataforma** (uma vez, pelo Sidney):
1. No painel de desenvolvedor do Mercado Pago (conta da plataforma), criar a
   aplicação e cadastrar o **redirect URI**:
   `https://admin.correvirtual.com.br/mercadopago/oauth/retorno` — tem de ser
   idêntico ao `MERCADOPAGO_OAUTH_REDIRECT_URI`.
2. Na mesma aplicação, configurar o **webhook** para
   `https://eventos.correvirtual.com.br/api/webhooks/mercadopago` (evento
   "Pagamentos") e copiar a assinatura secreta.
3. No secret `APP_ENV` do GitHub, acrescentar: `MERCADOPAGO_APP_CLIENT_ID`,
   `MERCADOPAGO_APP_CLIENT_SECRET`, `MERCADOPAGO_OAUTH_REDIRECT_URI`,
   `MERCADOPAGO_APP_WEBHOOK_SECRET` e `ALERTA_COBRANCA_EMAIL`. Segredo nunca
   vai para a MATRIX nem para o repositório.
4. Deploy (qualquer push). O botão "Conectar conta do Mercado Pago" aparece em
   `/admin/cobranca` quando as três primeiras estão preenchidas.

**Taxa da plataforma** — sem deploy:
`docker exec corre_app php artisan plataforma:taxa` (mostra) ou
`plataforma:taxa 0.80` (muda; vale a partir do próximo Pix).

**Agendador** — o deploy instala, de forma idempotente, no crontab do usuário
do SSH: `* * * * * docker exec corre_app php artisan schedule:run`. Hoje ele só
roda `mercadopago:renovar-tokens` (todo dia, 04:10 UTC). Conferir:
`crontab -l | grep schedule:run` na VPS.

**Alerta** — token que não renova ou Pix que não sai pela conta conectada:
log `CRITICAL` "ALERTA DE COBRANÇA" + e-mail para `ALERTA_COBRANCA_EMAIL`
(no máximo um por hora para o mesmo problema).

## Backup do banco

Roda sozinho: **cron da VPS, 03:20 todo dia**, via `/usr/local/bin/corre-backup.sh`. Faz `mysqldump` de `webcit29_eventos_prod` e `webcit29_eventos_dev`, comprime com gzip e guarda em `/opt/backups/corre/`, mantendo **14 dias**. A VPS é máquina diferente do banco (Hostgator), então a cópia já nasce fora do servidor de origem.

O script lê as credenciais do `src/.env` da aplicação — não tem senha escrita dentro dele.

**Cópia fora da VPS (R2).** O cron deixa o dump só no disco da VPS. Desde 2026-09-20 existe `php artisan backup:enviar-r2 <arquivo>`, que sobe um dump para `backups/` no bucket privado `correvirtual-privado` (sem domínio público; só a credencial do app chega lá) e confere o tamanho depois. O workflow "Zerar uso em produção" (abaixo) faz isso antes de apagar qualquer coisa. Ligar isso ao cron diário — para toda madrugada deixar uma cópia off-site — está no `docs/backlog.md`.

**Ele se recusa a aceitar um dump ruim.** Só vira backup o arquivo com mais de 1KB e que contenha `CREATE TABLE`; qualquer outra coisa é salva como `.SUSPEITO` e a rotação é suspensa, para que um dump quebrado nunca apague os backups bons.

```bash
# rodar na hora, fora do horário
ssh root@143.95.218.62 -p 22022 /usr/local/bin/corre-backup.sh

# ver o que existe e o histórico
ls -lh /opt/backups/corre/
tail -20 /var/log/corre-backup.log
```

**Restaurar** (o procedimento foi testado de verdade em 2026-08-29, restaurando produção num MySQL 5.7 temporário e conferindo as contagens):

```bash
# para um banco de teste antes de qualquer coisa — nunca direto em produção
zcat /opt/backups/corre/webcit29_eventos_prod_AAAA-MM-DD_HHMM.sql.gz \
  | mysql --host=srv238.prodns.com.br --user=<usuario> -p <banco_destino>
```

## Rodar um comando em produção sem SSH

Desde 2026-09-20 existe o workflow **"Comando em produção"** (`.github/workflows/comando.yml`): no GitHub, Actions → Comando em produção → *Run workflow* → digite o comando artisan **sem** o `php artisan` (ex.: `base:limpar-testes --listar`). Ele entra na VPS com os mesmos secrets do deploy e roda `docker exec corre_app php artisan <comando> --no-interaction`; a saída fica no log da execução. Vale para `admin:criar`, `og:gerar`, `base:limpar-testes` e qualquer outro — quem tem escrita no repositório tem o mesmo nível de confiança do deploy.

Nasceu porque a senha do root da VPS não estava à mão e nenhuma chave desta máquina era aceita lá. Se um dia precisar de SSH mesmo assim: `ssh root@143.95.218.62 -p 22022` (a senha está no painel da Hostgator, onde também se redefine).

### Zerar o uso da produção (antes do lançamento)

Tudo que a produção teve de inscrição e pagamento antes do lançamento foi teste. O workflow **"Zerar uso em produção"** (`.github/workflows/zerar-uso.yml`) apaga o **uso** e mantém o **catálogo**:

| Sai | Fica |
|---|---|
| inscrições, pagamentos, tokens de API, tokens de troca de senha, jobs | usuários, organizadores, eventos, modalidades, kits, equipes, patrocinadores, cupons |
| contadores zerados: `coupons.used_quantity`, `event_kits.sold`, `event_modalities.registered_count` | |

A ordem dentro do botão é a segurança, e não dá para pular etapa: **backup na VPS** (`corre-backup.sh`; se o dump de agora não for aceito, para) → **cópia no R2** (`backup:enviar-r2`) → **simulação** (`base:zerar-uso`, que lista cada inscrição com dono e evento) → e só se o input `confirmar` for exatamente `APAGAR`, o `base:zerar-uso --force`, numa transação.

Uso: Actions → Zerar uso em produção → *Run workflow*. **Primeiro sem nada** no campo (faz o backup, a cópia e mostra o que sairia), leia o log; depois com `APAGAR`.

### Limpar os dados de teste da produção (eventos mocados)

`base:limpar-testes` é outra coisa: apaga os seis eventos mocados (por slug, ver o comando) e **todo** o histórico de inscrição e pagamento; atletas e eventos reais ficam. A ordem segura, sempre pelo workflow "Comando em produção":

1. `base:limpar-testes --listar` — imprime usuários, eventos, cada inscrição com dono e evento (marcando o que está órfão), cupons e totais. **Leia antes de apagar**: se houver inscrição real de atleta em evento real, pare aqui — o comando não distingue.
2. `base:limpar-testes` (sem `--force`) — simulação: mostra as contagens e os eventos que sairiam.
3. `base:limpar-testes --force --evento-de-teste=<slug>` — apaga de verdade, numa transação. `--evento-de-teste` leva junto o evento de teste do fluxo (com kits, modalidades e cupons); sem a opção, ele fica.

O backup diário das 03:20 (seção "Backup do banco") é a rede se algo sair errado.

## Painel administrativo

O painel vive em `/admin` (ver `docs/specs/painel-admin.md`). Não existe tela para criar o primeiro administrador — é por linha de comando:

```bash
# cria um administrador novo
docker exec -it corre_app php artisan admin:criar admin@exemplo.com.br --organizador=1

# ou promove um atleta que já se cadastrou pelo site
docker exec -it corre_app php artisan admin:criar pessoa@exemplo.com.br
```

Sem `--organizador`, o comando pergunta qual usar (ou assume o único, se só houver um). Ao promover alguém, o e-mail é marcado como confirmado se ainda não estivesse — sem isso o login barra a entrada.

### Imagens de compartilhamento (Open Graph)

Toda arte enviada pelo painel gera na hora a derivada de 1200x630 que o WhatsApp e o Facebook usam no cartão do link. Para refazer as que já existem — depois de importar artes fora do painel, ou se o comando falhou:

```bash
# tudo: eventos com arte, banners de organizador e o padrão da plataforma
docker exec corre_app php artisan og:gerar

# um evento só
docker exec corre_app php artisan og:gerar --evento=12
```

Roda quantas vezes quiser: só reescreve as derivadas, nunca a arte original. Ver `docs/specs/frontend-publico.md` (Fase 2).

### Branches

`main` (protegida, deploy automático) ← PR ← `develop` (integração, sem deploy automático) ← PR ← `feature/*` / `fix/*`. Ver ADR 0004.

## Cadastrar um novo organizador (tenant)

Ainda não existe painel para isso (fase 2 — ver `backlog.md`). Hoje é manual:

1. Criar o registro em `organizers` (via tinker, seeder ou `psql`) com `domain` = o domínio que vai apontar pra esse organizador.
2. Apontar o DNS desse domínio para o VPS.
3. Adicionar o domínio em `server_name` no `docker/nginx/default.conf` e emitir certificado TLS (Let's Encrypt) pra ele.
4. Cadastrar os eventos desse organizador (hoje também manual/seeder).

## Troubleshooting

- **`app` não sobe / erro de conexão com banco:** ver seção de variáveis de ambiente acima.
- **Mudança em `.env` não é refletida:** Laravel cacheia config em produção. Rode `docker exec corre_app php artisan config:clear` (ou `optimize`, que já roda no deploy).
- **Erro 404 "Organizador não encontrado":** o host da requisição não bate com nenhum `organizers.domain` no banco — confira o seeder ou o registro manual.
- **Testes:** `docker exec corre_app php artisan test`. Rodam contra sqlite em memória (`phpunit.xml`), não tocam no banco real (Hostgator) — não precisa de setup extra.
