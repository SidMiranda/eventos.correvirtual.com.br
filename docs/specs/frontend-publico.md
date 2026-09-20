# Frontend público — redesign visual (Fase 1: Home v2)

Status: Implementado (aguardando revisão visual do usuário)

## Problema

O site público atual (`src/resources/views/index.blade.php` e componentes em `components/app/`) é funcional mas visualmente pobre: o "menu" do topo (`top-bar.blade.php`) é na verdade a barra de admin do template SB Admin reaproveitada (dropdowns de notificação, avatar de usuário — nada disso faz sentido pro visitante público de um site de inscrição pra corrida), o banner principal é uma imagem estática única, e o CSS está espalhado em `<style>` inline por componente sem um sistema de cores coerente.

Em `TEMPLATES/Front-End/` (template de referência já recebido) existem dois elementos que o organizador gostou e quer incorporar:
1. Um menu de duas camadas (`rd-navbar-top-panel` — barra fina em cima com contato/social; `rd-navbar-panel` — barra principal com logo e navegação).
2. Um banner rotativo (`swiper-container`) com várias telas, cada uma com título, texto de apoio e um botão de call-to-action.

A paleta de cores do template (vermelho/escuro genérico) não bate com a identidade do projeto — a decisão foi adaptar pra azul escuro + azul claro, reaproveitando as cores que **já existem** no código hoje: `#0d1b2a` (azul bem escuro, já usado como cor de texto em `about.blade.php`) e `#1a71b2` (azul médio, já usado em botões/links/rodapé em vários componentes).

## Requisitos

- [x] Menu de duas camadas (barra utilitária fina + barra de navegação principal) reaproveitando a estrutura visual do template, recolorido pra azul escuro/claro.
- [x] Banner rotativo com 3 slides, cada um com título, subtítulo e botão(ões) de CTA, avançando automaticamente e com navegação manual (setas + indicadores).
- [x] Sistema de cores centralizado (custom properties CSS) baseado nas cores já em uso no projeto, não uma paleta nova.
- [x] Sem novas dependências JS pesadas (sem jQuery/Bootstrap/Swiper/OWL — tudo em CSS + JS vanilla, ~100 linhas).
- [x] Nenhuma funcionalidade muda: login, logout, "Minhas inscrições", dropdown de usuário continuam nas mesmas rotas, só com visual novo.
- [x] Escopo restrito à Home (`/`) nesta fase — nenhuma outra página (`login`, `subscribe`, `event-details`, etc.) é afetada.

## Fora de escopo (nesta fase)

- Aplicar o redesign nas outras páginas (login, detalhe de evento, inscrição, minhas inscrições) — só acontece se o organizador aprovar a v2 da Home.
- Qualquer mudança de comportamento (regras de inscrição, pagamento, etc.) — só design.
- Reescrever `top-bar.blade.php`/`layouts/app.blade.php` originais — ficam intocados, servindo todas as páginas exceto a Home.

## Design

### Isolamento (por que nada mais quebra)

`layouts/app.blade.php` (usado por login, registro, verificação, inscrição, minhas inscrições, detalhe de evento) **não foi tocado**. A Home usa um layout novo e paralelo:

- `resources/views/layouts/app-v2.blade.php` — mesma estrutura básica (`head`, `response-message`, `scripts`), mas inclui `nav-v2` em vez de `top-bar`.
- `resources/views/components/app/nav-v2.blade.php` — menu de duas camadas.
- `resources/views/components/app/banner-v2.blade.php` — banner rotativo.
- `public/css/home-v2.css` — sistema de cores (custom properties) + estilos do nav/banner/seções da v2.
- `public/js/home-v2.js` — rotação do banner e toggle do menu mobile (vanilla, sem dependências).

`index.blade.php` passou a estender `layouts.app-v2` em vez de `layouts.app`. Todas as outras views continuam em `layouts.app` sem nenhuma alteração.

`layouts/app-v2.blade.php` carrega `home-v2.css`/`home-v2.js` com `?v={{ filemtime(...) }}` na URL — cache-busting automático. Sem isso, o navegador do organizador ficou preso numa versão em cache do CSS mesmo depois de um refresh normal (aconteceu comigo testando também) — com o timestamp do arquivo na query string, toda edição força o navegador a buscar a versão nova, sem depender de hard refresh manual.

### Paleta de cores (`public/css/home-v2.css`, `:root`)

| Token | Valor | Origem | Uso |
|---|---|---|---|
| `--cv-navy` | `#0d1b2a` | já usado em `about.blade.php` (`.cv-title`) | barra utilitária, rodapé, texto de destaque |
| `--cv-blue` | `#1a71b2` | já usado em botões/links/rodapé em todo o projeto | barra de navegação principal, CTAs |
| `--cv-blue-light` | `#4fa3d8` | tint calculado a partir de `--cv-blue` | hover, detalhes, gradiente do banner |
| `--cv-blue-pale` | `#eaf4fb` | tint bem claro de `--cv-blue` | fundo de seções alternadas |
| `--cv-ink` | `#1a1a1a` | já usado em `about.blade.php` (`.cv-badge`) | texto principal |
| `--cv-muted` | `#666666` | já usado em `about.blade.php` (`.cv-description`) | texto secundário |

### Menu de duas camadas (`nav-v2.blade.php`)

Revisado depois do primeiro round de feedback visual (o organizador viu nome do organizador e botões "Entrar"/"Criar conta" duplicados nas duas barras — corrigido):

- **Barra utilitária** (`--cv-navy`, ~36px de altura): só uma frase curta (tagline) à esquerda. Nada de marca ou autenticação aqui — é só um detalhe fino em cima. Chegou a ter ícones pequenos de rede social (Instagram, site oficial) à direita, mas foram removidos: o organizador reportou os ícones colados sem espaço e o texto centralizado, em uma sessão que persistia mesmo depois de limpar cache. Não foi possível reproduzir (medição direta via `getBoundingClientRect` mostrou 14px de gap e texto alinhado à esquerda, em qualquer largura testada) nem achar uma regra CSS que explicasse — removidos por segurança em vez de continuar investigando algo não reprodutível. Se quiser reintroduzir ícones sociais no futuro, considerar `<img>` normais em vez de SVG inline como primeira tentativa de isolar a causa.
- **Barra principal** (fundo `--cv-blue-pale`, tonalidade azul clara em vez de branco puro, sticky ao rolar): logo/nome do organizador à esquerda, navegação (`Eventos`, `Sobre`, `Patrocinadores`) no centro, e a área de autenticação (Entrar/Criar conta, ou Minhas inscrições/usuário/Sair quando logado) à direita, separada por uma linha divisória sutil. **Toda a autenticação mora só aqui agora** — antes estava duplicada nas duas barras. Em telas pequenas vira menu hambúguer (JS vanilla, só adiciona/remove uma classe), com a área de auth empilhada junto do resto do menu.

### Banner rotativo (`banner-v2.blade.php`)

3 slides com crossfade por CSS (`opacity` + `transition`, sem `transform3d` pesado), avançando a cada 6s, com setas prev/next e indicadores (dots) clicáveis. Cada slide: fundo com foto (ver "Geração de imagens" abaixo) + gradiente escuro por cima pra garantir contraste do texto, título grande, subtítulo, 1-2 botões CTA (ex.: "Ver Eventos", "Inscreva-se Já") apontando pra âncoras/rotas reais da Home.

Se o organizador tiver banner customizado (`images/organizers/{id}/banner.jpg`, já suportado hoje), uma versão **recortada** dele vira o slide 1 (prioridade sobre a imagem do Gemini, por autenticidade) — `public/images/home-v2/banner-1-organizer-cropped.jpg`, recorte de `[520,0,980,500]` do banner original (1500×500) feito uma vez via script (`System.Drawing`, não versionado), removendo o bloco de logo/texto à esquerda e mantendo a ponte de Mogi Guaçu (referência da cidade) que ocupa a maior parte da imagem. Cadeia de fallback em `banner-v2.blade.php`: recorte → banner cru do organizador (se o recorte não existir) → `banner-1.jpg` do Gemini → gradiente. A lógica de fallback de `main-banner.blade.php` original foi preservada, só a apresentação (rotação/CTA) é nova.

### Geração de imagens (Gemini) — executado

`app/Console/Commands/GenerateGeminiImages.php` rodou com sucesso (`GEMINI_API_KEY` configurada em `src/.env`, não commitada) e gerou `public/images/home-v2/banner-{1,2,3}.jpg` (corredores em grupo, pernas em movimento, comemoração na chegada com medalha — prompts sem texto/logo, pra não competir com o texto sobreposto do banner). Slide 1 usa o banner real do organizador quando existe (com fallback pra `banner-1.jpg` se não existir); slides 2 e 3 usam `banner-2.jpg`/`banner-3.jpg`. Pra gerar de novo (trocar as imagens): `docker exec corre_app php artisan images:generate-gemini --force`.

## Plano de testes

Mudança é puramente visual/apresentação (HTML/CSS/JS), sem lógica de negócio nova — não há teste automatizado PHP para isso. Verificação feita visualmente via Playwright MCP (screenshot desktop + mobile da Home v2) antes de considerar pronto pra revisão. Suite de testes existente (`php artisan test`) continua passando (nenhum controller/model tocado nesta fase).

## Critérios de aceite

- [x] `http://localhost/` carrega a Home v2 sem erro (200), com menu de duas camadas e banner rotativo.
- [x] Nenhuma outra rota (`/login`, `/subscribe/event/{id}`, `/my-subscriptions`, `/event/{id}`) muda de aparência ou comportamento.
- [x] Login/logout/"Minhas inscrições" continuam funcionando a partir do novo menu.
- [x] `php artisan test` continua verde.
- [ ] Organizador revisa e aprova (ou pede ajuste) — **pendente**, é o próximo passo depois desta rodada.

---

# Fase 2 — vitrine de realizados, topo do evento e cartão de compartilhamento

Status: Implementado (2026-08-30)

## Problema

Três coisas ficaram desalinhadas depois que as artes reais das provas entraram no lugar das imagens genéricas:

1. **A home enterrava os patrocinadores.** A seção era a última da página, depois de "sobre nós" — quem paga para aparecer aparecia onde ninguém mais estava rolando.
2. **O topo da página do evento tentava encaixar a arte.** A arte de corrida é retrato (576×1024, formato de story) e o topo é largo. Recortada, sumia justamente o nome e a data, que ficam na parte de cima do cartaz; inteira, virava um cartaz minúsculo entre duas faixas de fundo. As duas saídas eram ruins.
3. **O botão de compartilhar chegava sem imagem.** A meta `og:image` existia e apontava para a arte, mas a arte é retrato e pesa de 500 KB a 1,9 MB. O robô do WhatsApp monta um cartão deitado — então recortava o meio do cartaz — e desiste da prévia bem antes daquele peso, então na prática o link chegava sem imagem nenhuma.

Além disso, as provas que o organizador entregou **antes** desta plataforma existir não tinham lugar no site: elas viviam só no site antigo (`correvirtual.com.br`), como uma seção "Eventos Encerrados".

## Requisitos

- [x] Ordem da home: banner → próximos eventos → **patrocinadores** → **eventos realizados** → sobre nós → rodapé.
- [x] "Eventos realizados" é uma vitrine de cartazes, **sem link**: seis por linha no desktop, três no tablet, dois no celular.
- [x] A vitrine mostra tanto as artes das provas anteriores à plataforma quanto os eventos cadastrados aqui que já passaram da data.
- [x] O topo da página do evento é um degradê no azul do tema com o nome em texto grande, com a **mesma altura para todo evento**.
- [x] O compartilhamento leva a arte e o texto cadastrado do evento.
- [x] "Minhas inscrições" mostra a arte inteira, mantendo o card deitado.

## Fora de escopo

- CRUD da vitrine no painel. Enquanto a lista não muda, uma tabela e um formulário seriam estrutura sem uso — está registrado em `docs/backlog.md`.
- Trocar o formato das artes que o organizador manda fazer. O sistema se adapta ao que existe.

## Design

### A vitrine (`App\Support\GaleriaDeRealizados`)

Ela junta duas fontes que o visitante não tem por que distinguir:

| Fonte | De onde vem | Por que |
|---|---|---|
| Artes avulsas | `config/galeria.php`, por organizador | Provas anteriores à plataforma. Não têm inscrição, preço nem página — só a arte interessa. |
| Eventos do banco | Os que já passaram da data e têm arte | Sem isso, uma prova cadastrada aqui sumiria do site no dia seguinte à realização. |

O que sai é sempre a mesma forma — `['url' => ..., 'nome' => ...]` —, então a tela não sabe de onde cada cartaz veio. O evento do banco vem primeiro porque é o mais recente; a config é histórico mais antigo, e assim a vitrine fica em ordem decrescente sem precisar inventar data para arte avulsa.

A chave da config é o **id do organizador**: a vitrine de um não pode vazar no site do outro. Tem teste para isso.

Os arquivos ficam em `publico/organizadores/{id}/realizados/{arquivo}` no bucket. As nove artes vieram do site antigo, recomprimidas de PNG para JPEG — 2,5 MB viraram 959 KB.

O card (`components/app/arte-realizada.blade.php`) é um `<figure>` com uma `<img>` dentro. Sem `<a>` e sem botão: a prova acabou, não há o que fazer com ela.

### O topo da página do evento

Degradê fixo no azul do tema (`#05080d → #0d1b2a → #1a71b2`), 320px no desktop e 200px no celular, com o nome do evento em texto grande e uma linha com data e local. Texto de verdade, desenhado pelo navegador: nunca corta, nunca desfoca, e não custa uma requisição.

O `accent_color` de cada evento continua existindo e continua mandando no card de evento **sem** arte, na home e em "minhas inscrições". O que ele não faz mais é mandar no topo da página, que agora é igual para todos.

O `<h2>` que repetia o nome logo abaixo do topo saiu — com o nome grande no degradê, era a mesma frase duas vezes seguidas.

### O cartão de compartilhamento (`App\Support\ImagemOg`)

Uma terceira derivada da arte, gerada com GD: **1200×630** (a proporção que o WhatsApp e o Facebook esperam), com a arte inteira e nítida no centro, sobre uma versão desfocada e escurecida dela mesma. Assim o cartão fica na cor do evento sem precisar de arte extra, e nada do cartaz é recortado.

O desfoque é feito numa miniatura de 60×32 e depois ampliado — o filtro do GD é fraco e caro; aplicado num pedaço pequeno ele custa quase nada e, ampliado, o borrão sai bem mais suave do que na imagem inteira.

Resultado: as artes de até 1,9 MB viram cartões de 56 a 82 KB. Tem teste garantindo os dois números que vêm de fora (o formato e o teto de 300 KB).

Vale para todas as páginas, não só a de evento: o organizador e a plataforma também ganharam `og.jpg` de 1200×630, e é isso que autoriza o HTML a declarar `og:image:width`/`og:image:height`. **Nunca apontar `og:image` para uma imagem de outro formato sem trocar as dimensões junto.**

Quando o organizador sobe uma arte no painel, a derivada é gerada na hora (`ImagensDoEvento::gerarOg`), dentro de um `try/catch`: a arte já foi salva, e uma falha aqui não pode derrubar o cadastro do evento. Para o que já existia, `php artisan og:gerar`.

### "Minhas inscrições"

O card continua deitado, como sempre foi. O que muda é a arte: `object-fit: contain` sobre o degradê do evento, em vez de `cover`. No celular o card empilha e a coluna da arte ganha altura para o cartaz caber em pé.

### Patrocinadores vindos do cadastro

A seção era seis SVGs de exemplo ("Logoipsum") colados em `components/app/sponsors.blade.php`, herdados do template — trocar um patrocinador exigia mexer em código e subir deploy. Agora ela lê `sponsors` (ver `docs/specs/painel-admin.md`): ativos do organizador atual, na ordem que ele definiu, com o nome desempatando quem tem a mesma posição.

**Sem nenhum cadastrado a seção não aparece** — fileira vazia embaixo de um título é pior que não ter a seção. Mesma regra da vitrine de realizados.

Quem tem site vira link (`target="_blank"` com `rel="noopener"`, senão a página aberta ganha acesso a esta pela `window.opener`); quem não tem fica só como imagem. Sem logo enviado, aparece o nome em texto, para não abrir buraco na fileira.

Os logos ficam dessaturados em repouso e coloridos no hover: assim a fileira não briga com a arte dos eventos logo acima, e a marca ganha destaque quando a pessoa realmente para nela.

Os seis logos de exemplo foram **rasterizados para PNG e migrados para o cadastro**, então nada mudou de lugar no site e o organizador apaga ou substitui um a um pelo painel — em vez de ficarem presos no código.

### Rolagem até as seções, e o menu que nunca grudou

Os links do menu davam um salto seco. `scroll-behavior: smooth` no `html` resolve a rolagem, com `prefers-reduced-motion` respeitado — movimento na tela inteira incomoda de verdade quem tem sensibilidade vestibular.

Ao ajustar a margem da âncora (`scroll-margin-top`, para o título não parar atrás do menu) apareceu que **o menu nunca grudou**, apesar de estar declarado `position: sticky` desde a Home v2. Duas causas somadas, as duas vindas do CSS do template de painel (`top-bar.css`), que é carregado em todas as páginas públicas:

1. `body { overflow-x: hidden }` — `hidden` transforma o elemento num contêiner de rolagem, e isso desliga o `sticky` de quem está dentro. Trocado por `overflow-x: clip`, que corta igual sem criar contêiner.
2. `html, body { height: 100% }` — prende a caixa do body à altura da janela, e `sticky` só gruda enquanto a caixa de quem contém o elemento está na tela. Virou `height: auto; min-height: 100%`, preservando a intenção (ocupar a tela em página curta).

A altura da margem vem do próprio menu, medida pelo `home-v2.js` numa custom property (`--cv-nav-altura`): as duas barras mudam de tamanho conforme a largura, e um número fixo no CSS erraria em algum lugar.

`global.css`, `top-bar.css` e `forms.css` ganharam cache-busting, que faltava — sem isso a correção só apareceria para quem limpasse o cache.

### Limpeza da base de demonstração

Até 2026-08-30 nada no banco era prova real: eventos de seeder, kits todos a R$ 0,05 e inscrições feitas por quem estava testando. Isso aparecia em "minhas inscrições" como se fosse compromisso do atleta, apontando para evento que já tinha saído do site.

`php artisan base:limpar-testes` remove os seis eventos mocados (identificados por **slug**, não por id — os ids são diferentes em dev e produção) com suas modalidades e kits, e todo o histórico de inscrição e pagamento. Ficam os atletas, os eventos reais, o evento de teste do fluxo e as modalidades e kits deles.

Simula por padrão; só apaga com `--force`, dentro de uma transação. `LimparDadosDeTesteTest` protege a linha divisória: o que sai e, principalmente, o que não pode sair.

## Plano de testes

- `GaleriaDeRealizadosTest` — as artes da config aparecem, o evento passado do banco entra, o evento passado **sem** arte fica de fora (entraria como buraco na grade), os cartazes não são link, a galeria de um organizador não vaza no site do outro, a seção some quando não há nada, e os patrocinadores ficam entre próximos e realizados.
- `PaginaDoEventoTest` — o topo é o nome sobre o degradê e não a arte; o compartilhamento leva a derivada e o texto cadastrado; evento sem arte cai no cartão do organizador.
- `PatrocinadoresNoSiteTest` — a seção some quando não há patrocinador, o ativo aparece com logo e link, o sem logo mostra o nome, o inativo fica de fora, o de outro organizador não vaza, e a ordem é a que o organizador definiu.
- `ImagemOgTest` — sai sempre 1200×630 (de retrato, paisagem e quadrado), fica abaixo de 300 KB, e reclama quando o conteúdo não é imagem.
- Verificação visual via Playwright em 1440 / 900 / 390 na home, na página do evento e em "minhas inscrições".

## Consequência assumida

Um organizador que ainda não tem `banner.jpg` no bucket também não vai ter `og.jpg` — o cartão dele fica sem imagem, como já ficava antes. Vale para o organizador 2 (Borafitness), que hoje não tem imagem nenhuma no bucket.

---

# Fase 3 — galeria de fotos (2026-09-20)

Uma faixa no estilo do feed do Instagram, logo depois de "Próximos eventos": 100% da largura, fotos quadradas, 6 colunas × 2 linhas no desktop (12 fotos) e 2 colunas × 3 linhas no celular (as 6 primeiras). As fotos vêm do painel (`/admin/fotos`), não do Instagram — decisão do dono. Sem foto ativa, a seção não aparece, e o menu do site ganhou o link "Fotos". Spec completo, com a tabela, o recorte e o tratamento de orientação EXIF, em `docs/specs/galeria-de-fotos.md`.
