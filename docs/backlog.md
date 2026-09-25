# Backlog

Backlog vivo. Ao pegar um item pra trabalhar, siga o processo do `CLAUDE.md` (spec → plano aprovado → código → teste → atualizar este arquivo e o `CHANGELOG.md`). Ao achar um problema novo, adicione aqui em vez de deixar solto numa conversa.

Levantado na auditoria inicial de revitalização (2026-07-28). Nenhum destes itens foi corrigido ainda — só documentado.

## Escopo do MVP (fase 1 — agora)

Objetivo: site público bonito e funcional, um organizador, fluxo de inscrição + Pix correto e seguro, mesmo com poucos eventos e boa parte deles mocada via seeder.

- [x] Home v2 (nav de duas camadas + banner rotativo com imagens do Gemini, cores do template recolorido em azul) — ver `docs/specs/frontend-publico.md`. Mergeada em `develop`/`main` em 2026-08-02 junto com o primeiro deploy de produção.
- [ ] Replicar o redesign da Home v2 pras outras páginas públicas (login, detalhe de evento, inscrição)
- [ ] BUG-005 corrigido (P0 restante — envolve segurança de pagamento). BUG-001, BUG-002, BUG-003 e BUG-004 já corrigidos.
- [ ] BUG-006 corrigido (P1 — integridade multi-tenant básica)
- [x] Deploy de produção no ar — `https://eventos.correvirtual.com.br` (2026-08-02): VPS + Docker + Hostgator MySQL + TLS real (Let's Encrypt, renovação automática). Único pendente: webhook do Mercado Pago, ver abaixo.

## Antes do deploy de produção real

- [x] **Decidir onde mora o banco de produção de verdade** — resolvido em 2026-08-02: MySQL/MariaDB gerenciado na Hostgator (`webcit29_eventos_prod` / `webcit29_eventos_dev`), não Postgres. Ver `docs/decisoes/0005-banco-producao-hostgator-mysql.md`.
- [x] Atualizar o secret `APP_ENV` no GitHub com credenciais do banco de produção real.
- [x] Liberar o IP da VPS de produção (`143.95.218.62`) no Remote MySQL da Hostgator.
- [x] Apontar o DNS de `eventos.correvirtual.com.br` pra `143.95.218.62` e emitir certificado TLS real (Let's Encrypt via certbot, webroot, renovação automática já agendada — expira 2026-10-31).
- [x] Configurar `MERCADOPAGO_WEBHOOK_SECRET` de produção — resolvido em 2026-08-03, junto com a troca pra uma aplicação nova do Mercado Pago (a credencial anterior, "tentativa 1" no `.env`, dava BUG-008; a aplicação nova gerou um Pix de teste real com sucesso, confirmado via `MercadoPagoService::createPixPayment` diretamente na VPS).
- [x] **Definir rotina de backup do banco de produção** — feito em 2026-08-29. `mysqldump` agendado no cron da VPS (03:20 diário) para os dois bancos, com validação do dump antes de aceitar, retenção de 14 dias em `/opt/backups/corre/`, e restauração testada de verdade num MySQL temporário. Ver `docs/runbook.md` ("Backup do banco") e `CHANGELOG.md`.
- [ ] Trocar `docker/nginx/default.conf`: `server_name` do bloco HTTP ainda lista um IP antigo (`129.121.37.184`, de um VPS anterior) — inofensivo mas vale limpar.
- [x] ~~Reverter o preço de teste de R$ 0,05 para o preço real do kit~~ — **obsoleto desde 2026-08-29**: o `PixAmountResolver` e a variável `MERCADOPAGO_TEST_PRICE_ENABLED` foram removidos; o Pix cobra `subscriptions.price`, que é o preço do kit (os eventos de teste têm R$ 0,05 gravado como preço real do kit). Se a variável ainda estiver no secret `APP_ENV`, é só lixo — nada lê. (Anotado em 2026-09-20 ao revisar o fluxo de pagamento.)
- [x] **Credencial Mercado Pago trocada pra conta do Uéslei em 2026-08-02/03** (era do Sidney) — as tentativas anteriores ficaram comentadas em `src/.env` (não apagadas), dá pra reativar se precisar.

## Fase 2 (depois do MVP no ar)

- [ ] **Painel administrativo para o organizador** — em construção desde 2026-08-29, fatiado. Spec: `docs/specs/painel-admin.md`. Decisão de construir aqui (e não no Cubo): ADR 0006.
  - [x] Fatia 1: entrada do painel (`/admin`, papel `organizer_admin`, `admin:criar`), layout do SB Admin Pro e CRUD de **eventos**, com testes de isolamento entre organizadores.
  - [x] Fatia 2 (2026-08-29): CRUD de **categorias** e de **kits** aninhados no evento, CRUD de **equipes** por organizador, **upload das imagens** do evento direto para o R2, situação do evento deduzida das datas, e o cabeçalho do painel com a foto da ponte.
  - [ ] Fatia 3: ligar a **escolha de equipe na inscrição do atleta** — mostrar só as abertas e ativas do organizador atual, recusar equipe fechada ou de outro organizador mesmo que o id venha na mão. Exige `subscriptions.team_id`. O filtro já existe pronto e testado em `Team::escolhivelPeloAtleta()`.
  - [x] **Dev e produção deixaram de compartilhar o mesmo bucket R2** (2026-08-29). O problema chegou a acontecer: os ids de evento são diferentes nos dois bancos, e as artes da primeira carga saíram trocadas em produção. Corrigido com o bucket `correvirtual-arquivos-dev` e `?v={updated_at}` na URL (o CDN servia a versão antiga sob `Cache-Control: immutable`).
  - [ ] **O bucket de dev não tem domínio público.** Consequência a assumir de olhos abertos: no ambiente local a aplicação **lê** as imagens do CDN de produção mas **grava** no bucket de dev — imagem enviada testando aqui não aparece aqui. Resolver é dar um domínio público ao bucket de dev e apontar `ARQUIVOS_BASE_URL` local para ele.
  - [ ] Limite de vagas da categoria e estoque do kit são hoje só informativos — o sistema não bloqueia a inscrição ao atingir o limite.
  - [x] Trocar a imagem de um evento mantém o mesmo nome de arquivo, e a versão antiga ficava no cache do CDN. Resolvido em 2026-08-29 com `?v={updated_at}` na URL (`Arquivos::comVersao`), sem precisar versionar o nome do arquivo.
  - [x] **`admin.correvirtual.com.br` no ar** (2026-08-29): DNS apontado pelo Sidney, certificado Let's Encrypt emitido (vence 2026-11-27) e bloco próprio no nginx, com a raiz redirecionando para `/admin`. O painel aparece quando o código for para produção.
  - [x] `restart: unless-stopped` no `docker-compose.yml` (2026-08-29).
  - [ ] **Antes do primeiro push depois de 2026-08-29**: o `docker/nginx/default.conf` foi alterado direto na VPS para o painel funcionar hoje. O deploy roda `git pull`, que **falha** quando o arquivo rastreado está modificado — e o script não checa erro, então ele seguiria e reportaria "✅ Deploy finalizado com sucesso" sem ter atualizado o código. Rodar `git checkout -- docker/nginx/default.conf` na VPS antes de subir. Backup do arquivo antigo em `/root/nginx-default.conf.bak`.
  - [ ] Ver inscritos de um evento (era parte do item original desta linha; continua pendente).

- [ ] **Migrar os arquivos para o R2** (bucket `correvirtual-arquivos` já criado e populado em 2026-08-29). Spec: `docs/specs/armazenamento-r2.md`.
  - [x] Bucket criado, estrutura desenhada, 18 arquivos existentes copiados e conferidos.
  - [x] Centralizar a montagem da URL de imagem — feito em 2026-08-29 (`App\Support\Arquivos` + `ARQUIVOS_BASE_URL`). O disco local foi reorganizado para espelhar o bucket, então virar a chave é preencher uma variável.
  - [x] **Domínio do CDN definido e no ar**: `https://cdncorrevirtual.mobspot.com.br` (2026-08-29). `cdn.correvirtual.com.br` não era possível — domínio próprio no R2 exige a zona na Cloudflare, e `correvirtual.com.br` está na WebCit. Trocar para um domínio do `correvirtual.com.br` no futuro é mudar `ARQUIVOS_BASE_URL`, sem tocar em código.
  - [x] **Bucket privado separado** (`correvirtual-privado`, sem domínio). Corrige erro de desenho: prefixo `privado/` dentro do bucket público era acessível por URL, porque o domínio do R2 expõe o bucket inteiro.
  - [ ] Ligar o CDN em **produção**: `ARQUIVOS_BASE_URL=https://cdncorrevirtual.mobspot.com.br/publico` precisa entrar no secret `APP_ENV` do GitHub. Local já está ligado e validado.
  - [ ] Pendente com o Sidney: criar token R2 restrito só a `correvirtual-arquivos` (a cópia foi feita com a credencial do Cubo, que enxerga todos os buckets da conta).
  - [ ] Decidir o destino de `events.banner_url`, que perde a função com o caminho derivado do ID do evento.
  - [ ] Só depois de tudo validado: apagar `src/public/images/` e tirar os 6,1 MB de imagem do Git.
- [ ] **Vitrine de eventos realizados no painel.** Hoje a lista de artes de provas anteriores à plataforma mora em `config/galeria.php` — mudar exige deploy. Enquanto a lista não muda, tabela + CRUD seriam estrutura sem uso; quando o organizador quiser mexer sozinho, vira tabela (`organizer_id`, arquivo, nome, ordem) e uma tela igual à de equipes. Ver `docs/specs/frontend-publico.md` (Fase 2).
- [x] **Patrocinadores viraram cadastro** (2026-08-30): CRUD no painel, seção do site lendo o banco, e a seção some sozinha quando não há nenhum. Os seis logos de exemplo (`Logoipsum`) foram migrados para o cadastro — continuam lá até o Sidney substituir pelos reais, mas agora sai pelo painel, sem deploy.
- [x] **Galeria de fotos na home** (2026-09-20): faixa 6×2 / 2×3 alimentada por `/admin/fotos`, com envio em lote e recorte quadrado. O Instagram como fonte automática foi apresentado e descartado pelo dono — se voltar, vira uma sincronização gravando na mesma tabela `photos`. Spec: `docs/specs/galeria-de-fotos.md`.
- [ ] **Subir as primeiras fotos da galeria** em `/admin/fotos` antes do lançamento — sem foto, a seção não aparece.
- [ ] **Trocar os seis patrocinadores de exemplo pelos reais** em `/admin/patrocinadores`. Enquanto não trocar, a home mostra marca fictícia para quem visita.
- [x] **Cupons de desconto viraram cadastro** (2026-09-19): tela única em `/admin/cupons`, com filtro por evento e busca por código. O cupom é de um evento, o código é único por evento (e não global, para não acoplar organizadores) e o consumo do limite é atômico. Spec: `docs/specs/cupons-de-desconto.md`.
- [x] **Cupom aplicado na inscrição do atleta** (2026-09-20): campo no formulário com prévia ao vivo, desconto gravado em `subscriptions` (`list_price`, `discount_amount`, `price`, `coupon_id` — o retrato financeiro que um relatório futuro vai ler), Pix com o valor já descontado, e cupom de 100% confirmando sem pagamento. Cancelar não devolve o uso (decisão do dono). De passagem: `generatePix` deixou de aceitar inscrição de outro usuário e o webhook passou a gravar `confirmed_at`. Spec: `docs/specs/cupons-de-desconto.md`.
- [x] **Produção zerada antes do lançamento** (2026-09-19, à noite): saíram os 6 eventos mocados e o "Corrida de Teste do Fluxo 2026" (com 11 kits e 20 modalidades), as 8 inscrições e os 8 pagamentos de teste, e os contadores foram zerados. Ficaram os 7 eventos reais (#8–#14) com tudo, 4 usuários, 1 equipe, 6 patrocinadores e o cupom FULLPAS. Feito direto no banco da Hostgator a partir da máquina de dev (IP liberado no Remote MySQL na hora e retirado depois), com `base:limpar-testes --force --evento-de-teste=corrida-de-teste-do-fluxo-2026` e `base:zerar-uso --force`. Backup de antes em três lugares: R2 (`correvirtual-privado/backups/webcit29_eventos_prod_2026-09-19_2059.sql.gz`), `C:/Users/Sidney/Documents/backup-corre-virtual/` e a rotina da VPS. O workflow "Zerar uso em produção" continua no repo para a próxima vez.
- [ ] **O deploy derruba o site por alguns minutos quando o código novo precisa de tabela nova** (aconteceu em 2026-09-20, ~5 min de 500 na home ao subir a galeria de fotos). Causa: o `git pull` coloca o código novo no ar na hora (o `./src` é bind mount), mas o `migrate --force` só roda depois do `docker compose up --build` — e o rebuild da imagem (com a extensão `exif`, minutos) alonga a janela em que o código consulta uma tabela que ainda não existe. Correção proposta no `deploy.yml`: `git pull` → `composer install` → `migrate --force` → `optimize` **no container antigo** (o bind mount já tem o código novo e os caches ficam em `bootstrap/cache`, que persiste) → só então `up -d --build`. A janela cai de minutos para segundos. Enquanto não mudar: subir migration e código que depende dela de manhã cedo, e vigiar a home.
- [x] **Gestão de inscrições no painel** (2026-09-21): tela com filtros, relatório em PDF por evento, lista e ficha de atletas, e a inscrição cancelada passando a ficar registrada. Fecha a linha "Ver inscritos de um evento", que estava aberta desde a Fatia 2 do painel. Spec: `docs/specs/gestao-de-inscricoes.md`.
- [x] **A camiseta na inscrição é um campo solto** — *resolvido em 2026-09-24 (fatia 2, ADR 0007): o tamanho vem do kit, obrigatório quando o kit oferece, ausente quando não.* (posto em 2026-09-22 a pedido do dono, que pediu explicitamente "por enquanto é só uma coluna"). Hoje `subscriptions.shirt_size` é opcional e a tabela de medidas vive numa constante do `Subscription`, igual para todos os eventos. O certo: o **kit** declara se inclui camiseta e quais tamanhos, o campo só aparece nesse caso e aí **é obrigatório**. Enquanto não for, quem compra o kit "Com camiseta" pode deixar o tamanho em branco — e o organizador descobre isso na hora de fechar o pedido com o fornecedor. Vale conferir a lista antes de encomendar.
- [ ] **Limpeza pedida pelo organizador em 2026-09-23, PENDENTE.** Manter os eventos passados (#8, #9) como amostra e o Natal Mágico (#15) inteiro; apagar as 3 inscrições e o 1 pagamento (todos de teste: Sidney, Ueslei, Isabel) e o catálogo de teste dos futuros ainda não liberados (#10–#14: kits, modalidades, cupons — os eventos ficam). Backup feito antes (R2 `backups/webcit29_eventos_prod_2026-09-23_2229.sql.gz` + cópia local). A execução foi bloqueada pelo classificador de permissões do Claude Code; comandos prontos: `base:zerar-uso --force` (inscrições, pagamentos, contadores) e um delete de `coupons`/`event_kits`/`event_modalities` com `event_id IN (10..14)`.
- [x] **Promover Isabel Domingos a administradora** — feito em 2026-09-25 com `admin:criar` (organizador 1). `docker exec -e DB_DATABASE=webcit29_eventos_prod corre_app php artisan admin:criar isabel.dsmococa@gmail.com --organizador=1 --no-interaction` — ou pelo Actions "Comando em produção". A migration de 2026-09-22 procurou "Isbel" e, corretamente, não promoveu ninguém.
- [ ] **O limite do lote não é reservado atomicamente** (2026-09-24): dois atletas confirmando a última vaga no mesmo segundo entram os dois no lote anterior — uma inscrição a mais no preço antigo, não cobrança errada. Se o volume crescer, `SELECT ... FOR UPDATE` no lote dentro da transação.
- [ ] **Lote e categoria etária no painel de inscrições** (lista, ficha e PDF): a inscrição já grava `lot_id` e `age_category_id`, falta mostrar.
- [ ] **Exportar inscritos em CSV/Excel.** O PDF resolve conferência e impressão; planilha serve para cruzar dados (chip, resultado, camiseta). Reaproveita o `FiltroDeInscricoes` que já existe.
- [ ] **Marcar inscrição como paga pelo painel**, para quem pagou fora do sistema (dinheiro, transferência). Mexe no fluxo de dinheiro: exige decidir se gera `Payment` manual e se dispara o e-mail de confirmação.
- [ ] **Histórico de tentativas de inscrição.** Quem cancela e se inscreve de novo reaproveita a mesma linha (a unique `(event_id, user_id)` não deixa criar outra), então o cancelamento anterior some. Guardar cada tentativa exige derrubar a unique e repensar o "já inscrito".
- [ ] **`composer audit` acusa 39 advisories em 11 pacotes** (visto ao instalar o dompdf em 2026-09-21). Não foi investigado: pode ser ruído de dependência transitiva antiga. Vale uma passada antes de o volume de uso crescer.
- [ ] **Não existe "esqueci minha senha".** A troca de senha de quem está logado passou a existir em 2026-09-21 (`/alterar-senha`), mas quem perde a senha não tem saída sozinho — o Laravel já traz o fluxo de reset por e-mail pronto, falta ligar (a tabela `password_reset_tokens` existe e está vazia).
- [ ] **`forms.css` estiliza `button[type="submit"]` globalmente** e vaza para qualquer botão de envio da página — foi o que desalinhou o "Sair" do menu (corrigido em 2026-09-21 neutralizando no `home-v2.css`). A correção de raiz é restringir o seletor a `.form-container button[type="submit"]`, mas isso mexe em login, cadastro e inscrição: precisa de uma passada com olho em cada tela.
- [ ] **Os 6 eventos com o cartaz retrato no campo de banner.** Eles continuam no degradê (correto), mas se o organizador quiser banner no topo, é subir uma arte horizontal (algo como 1600×320) no campo "Banner" — o formulário agora explica isso. Rodar `eventos:medir-banners --force` em produção depois do deploy para registrar a proporção dos que já existem.
- [ ] **Depois do lançamento: retirar o workflow "Zerar uso em produção"** (ou exigir algo além de `APAGAR`). Com atleta real inscrito, um clique errado apaga inscrição paga.
- [ ] **Arquivos órfãos no R2 dos eventos apagados** (`publico/organizadores/1/eventos/{1..7}/`): as artes continuam no bucket sem registro apontando para elas. Inofensivo e pequeno; limpar quando houver um comando de varredura.
- [ ] **Existem 2 organizadores em produção**, e todos os usuários são do #1. Conferir o que é o #2 (resto de seeder?) antes de cadastrar o próximo organizador de verdade.
- [ ] **O evento #8 (Santa Edwiges) está sem kit**: já aconteceu (2026-08-30), então hoje só afeta a vitrine — mas se voltar a abrir inscrição, não tem o que vender.
- [ ] **Cron de backup passa a copiar o dump para o R2.** `backup:enviar-r2` já existe e é o que o workflow usa; falta o `corre-backup.sh` da VPS (não versionado) chamar `docker exec corre_app php artisan backup:enviar-r2 <dump>` depois de aceitar o dump. Aí toda madrugada deixa uma cópia off-site, e a retenção no bucket vira decisão (hoje o bucket guarda tudo que recebe).
- [ ] **Inscrição pendente nunca expira** — e agora segura o uso de um cupom para sempre. Já era o buraco da cobrança Pix vencida (`payments.expires_at` é gravado e nada limpa); com cupom ganhou uma consequência a mais. Resolver é um comando agendado que apaga (ou marca) pendentes vencidas — e aí decidir se o uso do cupom volta nesse caso, que é diferente do cancelamento pelo atleta.
- [ ] **Relatório financeiro por evento** — já dá para fazer lendo só `subscriptions`: bruto (`list_price`), descontos (`discount_amount`), líquido (`price`), por cupom (`coupon_id`), pagas vs. pendentes. Nenhuma coluna nova é necessária.
- [ ] **O fuso da aplicação é UTC** (`config/app.php`), então "hoje" vira o dia seguinte às 21h no horário de Brasília. Para o cupom isso é concreto: um que expira dia 20 morre às 21h do dia 20, três horas antes do que o organizador combinou. Vale para todas as datas do sistema (prazo de inscrição, data do evento), não só para cupom. Resolver é trocar o fuso para `America/Sao_Paulo` e conferir o que já está gravado — não é mudança isolada de uma tela.
- [ ] **Fonte Metropolis dá 404 no painel** (`/assets/admin/fonts/metropolis/*.otf`): o CSS do SB Admin Pro referencia arquivos que não vieram no template. O navegador cai na fonte de sistema e nada quebra visualmente, mas são quatro 404 por página no console. Ou trazer os arquivos, ou tirar o `@font-face`.
- [x] **Área do atleta ("Minha conta")** — 2026-09-25, spec `docs/specs/area-do-atleta.md`: inscrições em próximas/realizadas, perfil (nome, celular, cidade, PCD) e edição de camiseta/equipe até o "Alterações até" do evento. Ficou para depois:
  - [ ] **Estoque por tamanho de camiseta** — hoje a troca só respeita o prazo. Entra como uma condição a mais em `AlteracaoDeInscricao::aceitaTamanho()` (e na inscrição).
  - [ ] **Troca de e-mail pelo atleta** — exige confirmar o endereço novo antes de trocar o login.
  - [ ] **Troca de modalidade ou kit** — muda o preço; precisa de regra de diferença (cobrar ou devolver).
- [ ] Geração de número de peito (`bib_number`) após pagamento confirmado
- [ ] BUG-007 (throttle em login/registro/verificação)
- [ ] Papéis `organizer_admin` / `super_admin` (hoje só `athlete` é usado de fato)

## Fase 3 / ideias futuras (não priorizado)

- Check-in / retirada de kit no dia do evento
- ~~Lotes de preço por data~~ — estrutura e painel em 2026-09-23 (fatia 1, ADR 0007); o checkout passa a ler a grade na fatia 2
- ~~E-mail transacional de confirmação de inscrição/pagamento~~ (implementado em 2026-08-03) — ver `App\Mail\SubscriptionConfirmed`, `docs/specs/pagamentos-pix.md`.
- Relatório/exportação de inscritos para o organizador

---

## Bugs e débito técnico

### P0 — dinheiro e segurança de pagamento

**BUG-001 — ~~Preço da inscrição hardcoded em R$ 0,05~~ (corrigido em 2026-08-02)**
`SubscribeController::subscribe()` gravava `'price' => 0.05` fixo, ignorando o preço do `EventKit` escolhido — o valor que a `PixController` cobra de verdade via Mercado Pago. Corrigido: busca o `EventKit` (já validado que pertence ao evento) e usa `$kit->price`. Teste: `tests/Feature/SubscribeControllerTest.php` (`test_subscribe_charges_the_kit_price_not_a_fixed_value`).
*Spec relacionado:* `specs/eventos-e-inscricoes.md`, `specs/pagamentos-pix.md`.

**BUG-002 — ~~`modality_id`/`kit_id` sem integridade referencial~~ (corrigido em 2026-07-30)**
`subscriptions.modality_id` e `subscriptions.kit_id` eram colunas `string` soltas, sem foreign key — mesmo `EventModality`/`EventKit` sendo tabelas com PK numérica e o model `Subscription` declarar `belongsTo(EventModality::class)` / `belongsTo(EventKit::class)`. Corrigido pela migration `2026_07_30_000000_fix_subscriptions_modality_kit_foreign_keys.php` (colunas agora são `foreignId` com `restrictOnDelete()`) + validação em `SubscribeController::subscribe()` (`Rule::exists` checando que a modalidade/kit pertence ao `event_id`). Teste: `tests/Feature/SubscribeControllerTest.php`.

**BUG-003 — ~~Status `canceled` vs `cancelled` inconsistente~~ (corrigido em 2026-07-30)**
A migration define o enum como `pending|paid|cancelled` (2 L's), mas `SubscribeController::subscribe()` comparava com `'canceled'` (1 L) — comparação sempre verdadeira, e o branch de reativação nunca era executado. Decisão: manter o comportamento já implementado em `cancel()` (deleta a linha em vez de marcar `cancelled`); removido o branch morto de reativação. Teste: `tests/Feature/SubscribeControllerTest.php`.

**BUG-004 — ~~Webhook do Mercado Pago sem validação de assinatura~~ (corrigido em 2026-07-30)**
`MercadoPagoWebhookController::handle()` aceitava qualquer POST em `/api/webhooks/mercadopago` sem validar a assinatura/segredo que o Mercado Pago envia. Corrigido com `App\Services\MercadoPagoWebhookSignature` (valida o header `x-signature` via HMAC-SHA256, conforme algoritmo documentado pelo Mercado Pago) — requer `MERCADOPAGO_WEBHOOK_SECRET` configurado (`src/config/services.php` / `.env`); sem o secret, o webhook falha fechado (401). Testes: `tests/Unit/MercadoPagoWebhookSignatureTest.php`, `tests/Feature/MercadoPagoWebhookControllerTest.php`.

**BUG-005 — Sem validação de prazo, capacidade ou tenant na inscrição**
`SubscribeController::showSubscribeForm`/`subscribe` buscam o `Event` só pelo ID (`Event::findOrFail($eventId)`), sem checar: (a) se o evento pertence ao organizador do domínio atual, (b) se `registration_deadline` já passou, (c) se a modalidade atingiu `max_participants`. Um usuário logado no domínio do organizador A consegue se inscrever num evento do organizador B só sabendo o ID.

### P1 — autenticação / multi-tenant

**BUG-006 — `organizer_id` do usuário nunca é preenchido**
`RegisterController::register()` (`src/app/Http/Controllers/Auth/RegisterController.php`) não seta `organizer_id` ao criar o `User`, mesmo a coluna existindo e `app('currentOrganizer')` estando disponível no momento do cadastro. Usuários não ficam de fato vinculados ao tenant onde se cadastraram.

**BUG-007 — Sem throttle em login/registro/verificação de e-mail**
`/login`, `/register`, `/verify-email` não têm rate limiting. Baixo risco enquanto o site tem pouco tráfego, mas precisa entrar antes de ganhar tráfego real.

### P2 — código morto (candidatos a remoção — confirmar antes de apagar)

**DEBT-001** — `PaymentsController::pay()` (`src/app/Http/Controllers/Subscriptions/PaymentsController.php`) é um stub vazio, nenhuma rota aponta pra ele.

**DEBT-002** — `SubscribeController_old.php`, `Subscription_old.php`, `routes/web_old.php` não têm nenhuma referência ativa no projeto, mas foram tocados em commits recentes (`dba40cb`) — **confirmar com o dono do projeto** antes de apagar, pode ser material de referência da migração ainda em uso mental.

**DEBT-003** — ~~`resources/views/components/my-subscriptions.blade.php`~~ (removido em 2026-09-25, junto com o card antigo, na "Minha conta") e `components/app/old-top-bar.blade.php` são duplicatas órfãs. As views realmente usadas são `components/app/my-subscriptions.blade.php` e `components/app/top-bar.blade.php` (confirmado via grep nas views que usam `<x-app.*>`).

### P3 — infraestrutura / qualidade

**DEBT-004** — ~~Cobertura de teste é zero, e o único teste que existe está quebrado~~ (parcialmente corrigido em 2026-07-30). `RefreshDatabase` foi religado em `tests/Feature/ExampleTest.php` (precisava de um `Organizer` com `domain = 'localhost'`, já que `IdentifyOrganizerByDomain` roda em toda request e o host default de teste é `localhost`) e testes reais foram escritos junto da correção de BUG-002/003/004. Ainda falta cobertura para BUG-001 e BUG-005 quando forem corrigidos, e para os fluxos de auth/Pix em geral.

**DEBT-008** — `MercadoPagoService::getPayment`/`createPixPayment` são métodos estáticos que chamam o SDK do Mercado Pago direto, sem nenhum seam pra mock. Isso impede testar o caminho feliz do webhook (assinatura válida + pagamento aprovado) sem bater na API real — hoje só o caminho de rejeição (assinatura inválida) tem teste automatizado (`tests/Feature/MercadoPagoWebhookControllerTest.php`). Resolver exigiria transformar `MercadoPagoService` em algo injetável (classe com métodos de instância + binding no container, ou uma interface).

**DEBT-007** — Primeira subida do container `app` sem passar pelo `reset-dev.sh`/`reset-dev.bat` falha com HTTP 500: `storage/framework/{cache,sessions,views}` e `storage/logs` não existem no repo (diretórios vazios não vão pro git) e o container não os recria sozinho. Os scripts de reset já contornam isso manualmente (`mkdir -p` + `chmod`/`chown`); avaliar mover essa criação para um entrypoint do `docker/php/Dockerfile` pra não depender de rodar o script primeiro.

**DEBT-005** — ~~`MercadoPagoService::createPixPayment` chama `dd()` dentro do `catch`~~ (corrigido em 2026-08-03). Agora loga o erro (`Log::error`, com status/conteúdo da resposta do Mercado Pago) e devolve `null`; `PixController::generatePix` trata o `null` redirecionando pra "Minhas inscrições" com a mensagem "Estamos com instabilidade no pagamento no momento. Tente novamente mais tarde." — nenhum `Payment` é criado nesse caso. Testes: `tests/Feature/PixControllerTest.php` (usa `Mockery::mock('alias:...')` pra não bater na API real, mesma limitação do DEBT-008 — só o caminho de sucesso/falha do `PixController` tem cobertura, não o SDK em si).

**BUG-008 — ~~Credencial de produção do Mercado Pago rejeitada: "Unauthorized use of live credentials"~~ (resolvido em 2026-08-03)** — a primeira aplicação da conta do Uéslei foi recusada pela API do Mercado Pago (HTTP 401, `code 7`, `"Unauthorized use of live credentials"` — provavelmente aplicação não habilitada pra pagamentos live). Trocada por uma aplicação nova (mesma conta), que já veio com Client ID/Secret e webhook secret configurados — testado gerando um Pix de teste real com sucesso (`MercadoPagoService::createPixPayment` direto na VPS, id `170971602157`, status `pending`). A credencial anterior ficou comentada em `src/.env` como "tentativa 1", não apagada.

**DEBT-006** — Frontend inconsistente: Tailwind + Vite instalados mas o estilo real está em CSS solto por página (`src/public/css/*.css`). Endereçado parcialmente pela Home v2 (ver escopo do MVP); as outras páginas continuam nesse padrão até o redesign ser replicado.

**DEBT-009** — `head.blade.php` monta o caminho do favicon como `'images/organizers/'.$organizerId.'logo.png'` — falta uma barra entre o ID e `logo.png` (vira `images/organizers/1logo.png`, sempre 404). Achado navegando a Home v2 (console do navegador). Pré-existente em todas as páginas, não relacionado à Home v2 — não corrigido nesta rodada por estar fora do escopo (só design da Home).

**DEBT-010** — Não existe worker de fila (`queue:work`) rodando em nenhum ambiente, apesar de `QUEUE_CONNECTION=database` estar configurado — jobs despachados pra fila de verdade (`->queue()`) nunca seriam processados, ficariam parados na tabela `jobs` pra sempre. É por isso que `VerifyEmailCode` (cadastro) envia e-mail de forma síncrona e demora — e por isso que o e-mail de confirmação de inscrição (`SubscriptionConfirmed`) usa `dispatch(...)->afterResponse()` em vez de `->queue()`, que roda logo após a resposta HTTP sem precisar de worker. Resolver de verdade (subir um `queue:work` gerenciado por supervisor no container `app`) é uma melhoria futura — não bloqueia nada hoje, mas se o volume de e-mails crescer, `afterResponse()` ainda deixa a conexão do PHP-FPM ocupada até o e-mail terminar de enviar (só não trava a resposta ao cliente).

**Nota — remetente dos e-mails (checado em 2026-08-03, nada alterado):** `config/mail.php` usa `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` do `.env` — hoje `falecom@correvirtual.com.br` / "Corre Virtual". Não existe `reply_to` configurado em nenhum lugar (nem em `config/mail.php`, nem no `.env`) — o padrão do Laravel/SMTP nesse caso é o próprio remetente (`falecom@correvirtual.com.br`), então uma resposta do atleta ao e-mail de confirmação cai nessa caixa. Parece razoável já que é o e-mail de contato oficial (usado também no rodapé do site) — só sinalizando que não há um endereço de reply-to diferente configurado caso vocês queiram separar "envio" de "resposta" no futuro (ex.: enviar de `naoresponda@` e responder pra `falecom@`).
