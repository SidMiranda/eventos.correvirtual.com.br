# Changelog

Todas as mudanças relevantes do projeto são registradas aqui. Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).

Histórico anterior a este arquivo (todo o desenvolvimento inicial do projeto) pode ser consultado via `git log` — não foi reconstruído retroativamente aqui.

## [Unreleased]

### Preço por modalidade × kit × lote — fatia 1: estrutura e painel (2026-09-23)
- **O checkout não muda nesta fatia.** O atleta continua pagando `event_kits.price`. O que entra é a estrutura nova e as telas do organizador — para a grade migrada ser conferida em produção antes de qualquer atleta ser cobrada por ela (ADR 0007, `docs/specs/precos-lotes-e-categorias.md`).
- **Cinco tabelas novas**: `event_lots` (janelas de vigência com limite opcional), `event_prices` (o valor em `modalidade × kit × lote`), `event_kit_modality` (em quais modalidades cada kit vale), `kit_options` (variação do kit — só tamanho de camiseta por ora) e `age_categories` (desconto por idade). Colunas: `events.age_criteria` e, na inscrição, `lot_id`, `age_category_id` e `age_discount_amount` — o desconto de idade **não** entra em `discount_amount`, que continua sendo só o cupom.
- **Migração de dados** (`App\Support\PrecosLegados`, testada): cada evento ganhou "Lote 1" aberto, cada kit ficou disponível em todas as modalidades e o preço de cada kit foi copiado para cada célula da grade. Kits ganharam os 10 tamanhos, **exceto os de nome "sem camiseta"** — a única heurística, para o kit sem camiseta não passar a exigir tamanho. `php artisan eventos:migrar-precos` repete o processo para o que ficou faltando, sem duplicar.
- **Painel**: abas novas no evento — **Lotes**, **Preços** (a grade: linhas = modalidade × kit vinculado, colunas = lotes; célula vazia = combinação não vendável) e **Categorias**. O formulário do kit ganhou as caixas de **modalidades** e de **tamanhos**; o do evento, o **critério de idade** (ano-calendário ou data exata).
- **Cascata de descontos** já na conta (`PrecoDaInscricao::de()`): a idade abate o preço base, o cupom abate o que sobrou. Decisão do dono entre quatro opções. O caminho antigo `::para($kit)` continua e dá o mesmo resultado de antes.
- Teste do `retrato` da inscrição atualizado: passou de 4 para 6 colunas.

### Tamanho da camiseta na inscrição (2026-09-22)
- **Novo campo "Tamanho da camiseta"** logo abaixo do kit, com a tabela do fornecedor: seis tamanhos de camiseta (P a EXG) e quatro de baby look (BLP a BLGG), cada um mostrando a medida — "GG — 59 x 76 cm". Agrupados em dois `optgroup` para o atleta não confundir as duas réguas.
- **Uma coluna só (`subscriptions.shirt_size`)**, como combinado: a tabela de medidas é a mesma para todos os eventos hoje, e o fluxo certo — cada evento declarando o que oferece — fica para depois.
- **O campo é opcional**, e a razão é concreta: os kits do evento em produção são "Sem camiseta" e "Com camiseta". Exigir tamanho de quem comprou o kit sem camiseta travaria a inscrição por nada. A dica na tela diz "escolha só se o kit que você marcou inclui camiseta". Quando o kit passar a declarar se tem camiseta, a exigência vem dele.
- A lista é fechada no servidor: tamanho fora da tabela é recusado, porque só chega ali por formulário adulterado.
- No painel: o tamanho aparece na lista de inscrições e, com a medida por extenso, na ficha do atleta.
- 8 testes novos. Suíte: **413 testes, 1330 asserções**.

### Placeholder do cupom na inscrição (2026-09-22)
- O campo de cupom agora diz só **"Tem um CUPOM?"** no lugar de "Cupom de desconto (opcional)". A dica logo abaixo começava com a mesma pergunta e passou a ir direto ao ponto ("Escolha o kit, digite o código e aplique…"), para o campo e a linha de baixo não repetirem a mesma frase.

### CPF de verdade, e CPF do responsável para menor de idade (2026-09-22)
- **O CPF não era conferido.** A regra do cadastro era `size:11` e nada mais: **`11111111111` entrava**. Agora passa pelos dígitos verificadores (`App\Rules\Cpf`, a conta oficial da Receita), que também recusa número repetido. O CPF identifica o atleta na largada e no comprovante de pagamento — número inventado só aparece como problema no dia da prova, quando não dá mais para corrigir. **Isto vale para o CPF de todo mundo, não só o do responsável.**
- **Campo "CPF do responsável"**, que aparece quando a data de nascimento informada é de menos de 18 anos. Obrigatório, com a mesma conta de dígitos, e **não pode ser o CPF do próprio atleta**. Menor de idade não responde por si num contrato, e a inscrição é um: tem pagamento, termo e risco físico.
- O campo aparece e some na tela por JavaScript, e some limpo — mudou a data para maior de idade, o que estava digitado não vai junto. Mas **quem decide é o servidor** (`Rule::requiredIf` sobre a data de nascimento): esconder no front não é validar.
- Na ficha do atleta, no painel, o CPF do responsável aparece junto do resto do cadastro.
- 12 testes novos. Suíte: **405 testes, 1285 asserções**.

### Acesso ao painel para mais uma administradora, e o placeholder da cidade (2026-09-22)
- **Migration para promover "Isbel Domingos" a administradora — que não encontrou ninguém.** O pedido veio sem e-mail e a busca foi pelo nome; a trava (só promove com exatamente uma pessoa com esse nome) fez o que devia: o cadastro dela é **"Isabel"** Domingos, com A, e a migration não promoveu ninguém (visto em 2026-09-23). A promoção fica para `admin:criar isabel.dsmococa@gmail.com --organizador=1`, pelo dono.
- **Placeholder do campo cidade** virou só "Cidade". O anterior explicava o funcionamento ("digite 3 letras e escolha na lista") dentro do campo, o que ficava comprido e feio no meio do formulário.

### Cidade no cadastro do atleta e equipe na inscrição (2026-09-22)
- **Campo "Cidade" no cadastro**, vinculado de verdade: nova tabela `cities` com os **5.571 municípios do IBGE** (código oficial, nome e UF) e `users.city_id` apontando para ela. Nada de texto digitado — "Mogi Guaçu", "mogi guacu" e "MOGI GUAÇÚ" seriam três cidades diferentes na hora de contar de onde vem o pessoal.
- **A busca começa com 3 letras** e é **insensível a acento e caixa**: a coluna `name_normalized` guarda o nome sem acento e em minúsculas, porque ninguém digita "São Paulo" com til no celular. Quem **começa** pelo termo vem primeiro — digitou "mogi", "Mogi Guaçu" antes de "Itamogi". Devolve no máximo 20.
- **A lista mora no nosso banco**, não na API do IBGE. Consulta externa a cada tecla colocaria a inscrição de alguém na dependência de um serviço de fora responder a tempo. O arquivo `database/data/municipios-ibge.json` é versionado (290 KB) e entra pelo `php artisan cidades:importar --force`, que roda no deploy — é upsert pelo código do IBGE, então repetir não duplica nem desfaz vínculo.
- **Cidade é obrigatória e tem de ser escolhida da lista**: o que vale é o `city_id`, não o texto digitado — digitar o nome e não clicar na sugestão não conta. Quem já tinha conta continua sem cidade e continua entrando normalmente; a exigência é do cadastro, não do login.
- **Campo "Equipe" na inscrição**, logo depois do cupom: texto livre, só letras, números e espaço, até 50 caracteres, gravado em **CAIXA ALTA** (`mb_strtoupper`, para "são" virar "SÃO" e não "SãO") e sem espaço sobrando — "corre mogi", "Corre Mogi" e "CORRE  MOGI" viravam três equipes na hora de contar quem trouxe mais gente.
- A coluna é **`team_name`** e não `team_id`: existe cadastro de equipes no painel desde 2026-08-29, mas na primeira prova ninguém sabe ainda quais assessorias vão aparecer, e escolher de uma lista vazia seria pior. O nome do campo deixa claro que é texto solto e guarda o lugar para o vínculo de verdade.
- No painel: a **equipe** aparece na lista de inscrições e na ficha do atleta; a **cidade**, na lista de atletas e na ficha.
- 21 testes novos. Suíte: **393 testes, 1251 asserções**.

### "Sobre nós" da home vira tela do painel (2026-09-22)
- **Tag, título, texto, rótulo do botão e link** do bloco de apresentação da home estavam escritos no Blade. O efeito que ninguém tinha notado: **o mesmo texto saía em todos os sites da plataforma** — o site do Borafitness exibia "a **Corre Virtual** é a comunidade que combina saúde, diversão…". E o botão apontava para `href="#"`: nunca levou a lugar nenhum.
- **Nova tela `/admin/sobre`** (menu "Sobre nós"), registro único por organizador: cinco campos, upload da foto e remoção dela. Sem `index` nem `destroy` — é uma seção do site, não uma lista.
- **Cinco colunas novas em `organizers`** (`about_badge`, `about_title`, `about_text`, `about_button_label`, `about_button_url`). A migration devolve o texto que estava no Blade **só para a Corre Virtual**, que é de quem ele fala: o site entra no ar hoje e não podia mudar de aparência por causa disso. Os demais organizadores nascem vazios.
- **O layout não mudou em nada** — CSS do componente intocado, byte a byte. Medido no navegador antes e depois: as duas metades dão 608 e 528px a 1440px de largura nas duas versões, e no celular empilham em 305px cada, sem rolagem horizontal.
- **Sem título e texto, a seção inteira some** da home (cabeçalho "SOBRE NOS" junto) — meia seção vazia ao lado de uma foto é pior que seção nenhuma. **Sem link, o botão não aparece**, em vez do `href="#"` de antes. Sem foto, o `onerror` esconde a metade da direita e o texto ocupa a largura toda.
- **Uma regra só para todo texto livre do painel** (`App\Support\TextoDoSite`): escape primeiro, `**negrito**` depois, quebra de linha por último. As duas palavras em negrito do texto original continuam em negrito, e descrição, cronograma e informações da inscrição do evento passaram a usar a mesma regra — tinham cada uma a sua chamada solta de `nl2br(e(...))` na view.
- **A foto ganhou versão na URL.** O caminho no bucket é fixo e a gravação usa `Cache-Control: immutable`: sem o `?v={updated_at}`, trocar a foto pelo painel deixaria o CDN servindo a antiga por um ano. Quem troca só a imagem recebe um `touch` para mover a versão.
- Saiu junto o `<script async src="//www.instagram.com/embed.js">` que o componente carregava: sobra de uma versão antiga, sem nenhum embed na página, custando uma requisição externa em todo carregamento da home.
- 24 testes novos. Suíte: **372 testes, 1191 asserções**.

### Bloco "Inscrição" da página do evento vira campo do cadastro (2026-09-22)
- Mesmo caso do cronograma, um dia depois: a frase **"A inscrição dá direito ao kit exclusivo do evento"** estava chumbada no Blade e saía igual em toda prova. Era o lugar natural para dizer o que a inscrição inclui, onde e quando se retira o kit e como se troca o tamanho da camiseta — e não havia onde escrever isso.
- **Nova coluna `events.registration_info`** (text, nullable) e um textarea de 5 linhas no formulário, logo abaixo do cronograma. Mesmo `nl2br(e(...))`: quebra de linha e linha em branco valem, HTML digitado não.
- **O bloco não some quando o texto está vazio**, diferente do cronograma: a linha "Encerramento das inscrições" é calculada de `registration_deadline` e continua sempre lá. Some só o parágrafo livre.
- 7 testes novos. Suíte: **348 testes, 1127 asserções**.

### Cronograma da prova vira campo do cadastro (2026-09-21)
- **O bloco "Cronograma" da página do evento era texto fixo no Blade** — herança do tempo em que o catálogo era mocado. Toda prova, sem exceção, exibia "04h abertura do estacionamento, 05h30 largada 10km, 06h largada 5km, 08h30 premiação", inclusive a que larga às 7h e não tem 10km. Não havia onde corrigir: o organizador não tinha campo nenhum.
- **Nova coluna `events.schedule`** (text, nullable) e um bloco no formulário do painel, logo **depois da Cor do evento**: um textarea de 7 linhas, um horário por linha.
- **A quebra de linha e a linha em branco valem.** O texto sai `nl2br(e(...))`: o `e()` escapa o que foi digitado e só depois entram os `<br>` — assim o cronograma aparece na página exatamente como foi escrito, sem abrir a porta para HTML vindo do formulário. Serve para separar um dia do outro ("SÁBADO — retirada do kit", linha em branco, "DOMINGO — largada").
- **A descrição do evento ganhou o mesmo tratamento.** Ela já era um textarea, mas saía dentro de um `<p>` com `{{ }}`: o organizador escrevia em parágrafos e o atleta lia um bloco corrido.
- **Sem cronograma, o bloco não aparece.** Evento com a programação ainda em aberto mostra uma seção a menos — melhor que mostrar o horário de outra corrida.
- 9 testes novos. Suíte: **341 testes, 1105 asserções**.

### Banner do evento: sem canto arredondado e sem corte no celular (2026-09-21)
- **Tirado o `border-radius`** do topo da página do evento. O banner encosta nas bordas, e canto redondo ali deixava um respiro esquisito contra o fundo. Vale para os dois casos — com banner e com o degradê.
- **No celular o banner era cortado.** A regra `@media (max-width: 768px)` fixava `height: 200px` para o topo, o que vencia o `height: auto` do banner com imagem: com `cover`, a arte perdia as laterais — justo onde ficam as logos de quem realiza e patrocina. Agora o banner com imagem mantém a proporção da própria arte também no celular (num 5:1 a 375px de largura, o quadro fica com 75px de altura e mostra tudo).
- Sem teto de altura no celular, pelo mesmo motivo: numa tela estreita, uma arte alongada vira uma faixa baixa, nunca uma parede.

### Gestão de inscrições: tela, filtros, relatório em PDF e ficha do atleta (2026-09-21)
- **Tela de inscrições** em `/admin/inscricoes`: o organizador finalmente vê quem se inscreveu. Filtros por evento, por situação (pagas, aguardando, canceladas), "só com desconto" e busca por nome, e-mail ou CPF — todos combináveis e preservados na paginação. O CPF é comparado só pelos dígitos: no banco está sem pontuação e a pessoa digita com.
- **Totais que acompanham o filtro**: inscrições, pagas, aguardando, canceladas, arrecadado e descontos concedidos. O que se lê no topo é sempre o que está na tabela abaixo. Arrecadado e descontos contam só as **pagas** — inscrição pendente ainda não é dinheiro — e saem de `subscriptions` (`price`, `discount_amount`), sem reconstruir nada a partir do kit ou do cupom.
- **Relatório em PDF** (`barryvdh/laravel-dompdf`), servido com `Content-Disposition: inline`: **abre em aba nova em vez de baixar**, como pedido. Exige evento escolhido — lista com provas misturadas não serve no papel, é por evento que se confere largada, kit e lote —, e sem evento o botão fica desabilitado explicando por quê. Traz cabeçalho do organizador e do evento, os filtros aplicados em português, a lista com CPF e cupom, e os totais.
- **A tela e o PDF usam o mesmo filtro** (`App\Support\FiltroDeInscricoes`). Se cada um tivesse a sua cópia, a primeira diferença entre as duas seria um número errado num papel entregue a alguém.
- **Lista e ficha de atletas** em `/admin/atletas`, só consulta. "Atleta do organizador" não existe no banco — a conta é da plataforma; o que liga alguém a este painel é ter ao menos uma inscrição num evento dele. Por isso atleta sem vínculo dá 404, e a ficha mostra só as inscrições nos eventos do organizador.
- **Inscrição cancelada passa a existir.** Até aqui cancelar **apagava** a linha (decisão do BUG-003, em 2026-07-30), e o organizador não tinha como saber que alguém desistiu: a inscrição sumia da base. Agora grava `cancelled` + `cancelled_at`, e a cobrança pendente continua sendo apagada (ninguém paga um Pix de inscrição cancelada).
- **A grafia estava errada nas views.** O enum do banco usa dois L (`cancelled`) e as duas telas de "minhas inscrições" comparavam com um só — comparação que nunca batia, escondida porque o caso não existia. Passaria a mostrar "Cancelled" cru para o atleta. Corrigido.
- **Reinscrever depois de cancelar reaproveita a linha**: a unique `(event_id, user_id)` não deixa criar uma segunda. A inscrição volta a pendente com os dados da nova tentativa; o uso do cupom anterior não volta e a nova consome um uso próprio.
- 32 testes novos. Suíte: **332 testes, 1080 asserções**. Spec: `docs/specs/gestao-de-inscricoes.md`.

### Três correções: menu, alterar senha e o banner do evento (2026-09-21)
- **O "Sair" do menu da home estava 10px mais baixo** que os outros itens. A causa não era o menu: `forms.css` estiliza **todo** `button[type="submit"]` da página — é o botão verde dos formulários de login e inscrição — e de lá vinham `margin-top: 10px` e `width: 100%`, que vazavam para o botão do logout. Medido no navegador: desvio de 4,75px entre o topo do botão e o do link ao lado; agora os três itens têm o mesmo centro.
- **"Alterar senha" virou uma tela de verdade.** O item existia no menu desde sempre apontando para `#!` — um link que não levava a lugar nenhum. Agora pede a senha atual (sem isso, quem senta numa sessão aberta troca a senha e toma a conta), exige confirmação, recusa repetir a senha antiga e regenera a sessão ao salvar. O arquivo `PasswordResetController.php` que existia era código morto: declarava `class PasswordController`, nome divergente do arquivo, então o autoload nunca o carregou. Recuperação por e-mail ("esqueci minha senha") continua não existindo — está no backlog.
- **O banner do evento voltou a aparecer** — mas só quando é um banner de verdade. A imagem enviada nunca era exibida: em 2026-08-30 o topo virou um degradê fixo porque o campo recebia o cartaz da prova (retrato), que num quadro largo fica recortado justo no nome e na data. Só que o campo continuou no painel, e um banner horizontal legítimo (1600×320) subiu sem nunca aparecer.
- A distinção agora é medida: **`events.banner_ratio`** guarda a proporção da imagem, calculada no upload. Acima de 2:1 é banner e vai para o topo; abaixo disso continua no degradê. Dos 7 eventos em produção, 1 tem banner horizontal e 6 têm o cartaz retrato no campo — os números que motivaram o corte.
- **O quadro usa a proporção da própria imagem** (não os 320px fixos): com altura fixa, um banner 5:1 era cortado nas laterais, justo onde ficam as logos de quem patrocina. Teto de 420px para um banner pouco largo não empurrar o evento para fora da primeira tela.
- **Com banner, o nome sai da vista mas continua no HTML.** A arte já traz nome, distâncias, data e patrocinadores; escrever por cima duplicava tudo — e data e local já aparecem nos blocos logo abaixo. O `<h1>` fica acessível a buscador e leitor de tela.
- `php artisan eventos:medir-banners` preenche a proporção de quem já tinha imagem enviada (simula por padrão, grava com `--force`), e o formulário do painel passou a dizer o que espera em cada campo.
- 16 testes novos. Suíte: **300 testes, 951 asserções**.

### Galeria de fotos na home, alimentada pelo painel (2026-09-20)
- **Nova faixa "Galeria de fotos"** logo depois de "Próximos eventos", no estilo do feed do Instagram: 100% da largura, fotos quadradas, **6×2 no desktop** (12 fotos) e **2×3 no celular** (as 6 primeiras, o resto escondido por CSS). Sem foto ativa, a seção não existe. O menu do site ganhou "Fotos".
- **Cadastro em `/admin/fotos`**, do organizador (como equipe e patrocinador), com **envio em lote** — até 10 por vez; o PHP do container aceita 16 MB por envio, então três ou quatro fotos de celular de cada vez, e o formulário diz isso. Legenda (vira `alt`/`title`), link opcional (o post no Instagram; exige `https://`), ordem e liga/desliga, foto a foto.
- **Duas derivadas por foto, JPEG**: o quadrado da grade (700×700 — ~350 px na tela, o dobro no arquivo para não borrar em tela retina), recortado pelo centro e preenchendo, porque as fotos chegam de proporções variadas; e a foto inteira (cabendo em 1000 px), guardada para um "ver inteira" futuro. As fotos ficam encostadas, sem vão, e no hover a foto **cresce um pouco** dentro do quadrado. (A primeira versão trocava pela inteira em fade; `contain` a deixava menor que o recorte — parecia encolher — e o dono pediu o zoom.) O painel recebe foto de 3–5 MB e 4000 px; o original não fica.
- **Foto em pé aparece em pé.** Foto de celular vem "deitada" nos pixels com a tag EXIF pedindo o giro; o navegador obedece, o GD não. A extensão `exif` entrou no `docker/php/Dockerfile` e a derivada é girada antes do recorte. Sem a extensão, grava sem girar — nunca falha por isso.
- **Linha existe ⇒ imagem existe**: sem `has_image`. O painel cria a linha, grava a derivada e, se a gravação falhar, apaga a linha na hora — arquivo que falha é listado no erro sem derrubar os outros do lote.
- "Fotos" e nunca "galeria" no código: `config/galeria.php` e `GaleriaDeRealizados` já são a vitrine de provas realizadas. Tabela `photos`, model `Photo`, seção `#fotos`.
- Spec: `docs/specs/galeria-de-fotos.md`. 26 testes novos. Suíte: **284 testes, 899 asserções**.

### Mais respiro entre as seções da home (2026-09-19)
- **Dobrou o espaço** entre os blocos da home (Próximos eventos, Patrocinadores, Realizados, Sobre nós): 60px → 120px entre um bloco e o outro, e 48px → 96px entre cada título e o bloco dele. Estava tudo grudado. Override em `home-v2.css` (só a home tem essas seções), sem tocar `global.css`/`event-cards.css`.

### Zerar o uso da produção com backup em dois lugares (2026-09-20)
- **Workflow "Zerar uso em produção"** (`.github/workflows/zerar-uso.yml`): o sistema vai ao ar de verdade na segunda seguinte, e tudo que a produção tinha de inscrição e pagamento era teste. O botão apaga o **uso** (inscrições, pagamentos, tokens de API e de troca de senha, jobs) e zera os contadores que esse uso inflou (`coupons.used_quantity`, `event_kits.sold`, `event_modalities.registered_count`), mantendo o **catálogo** (eventos, modalidades, kits, equipes, patrocinadores, cupons) e os usuários.
- **A ordem é a segurança e não dá para pular**: backup na VPS pelo `corre-backup.sh` (se o dump de agora não for aceito — o script recusa dump ruim —, o workflow para antes de encostar em qualquer coisa) → cópia no R2 → simulação com a lista de cada inscrição → e só com o input `confirmar=APAGAR`, o `--force` numa transação. Sem `APAGAR`, é um botão de "mostra o que sairia" que ainda deixa backup novo em dois lugares.
- **`base:zerar-uso`** (novo): o comando por trás do botão. Diferente de `base:limpar-testes`, não apaga evento nenhum. Simula por padrão.
- **`backup:enviar-r2 <arquivo>`** (novo): sobe um dump para `backups/` no bucket privado `correvirtual-privado`, em stream, e confere o tamanho depois. O backup diário ficava só no disco da VPS; agora existe o caminho para a cópia off-site — ligar ao cron está no backlog.
- 5 testes novos. Suíte: **258 testes, 794 asserções**.

### Comando em produção pelo GitHub, e a limpeza de teste com listagem (2026-09-20)
- **Workflow "Comando em produção"** (`.github/workflows/comando.yml`, `workflow_dispatch`): roda `php artisan <comando>` na VPS pelo botão *Run workflow*, com os mesmos secrets do deploy, e mostra a saída no log. Nasceu porque a senha do root não estava à mão e nenhuma chave da máquina de desenvolvimento era aceita na VPS — e porque operar o sistema (criar admin, gerar OG, limpar base) não deveria depender de SSH. O comando entra pelo ambiente da action (`envs`), não interpolado no script, e leva `--no-interaction` para nunca travar esperando resposta.
- **`base:limpar-testes --listar`**: imprime usuários, eventos com contagens, cada inscrição com dono e evento (marcando órfãos de evento ou usuário que não existe mais), cupons e totais. É o "revisar antes de apagar", pensado para ser lido no log do workflow.
- **`base:limpar-testes --evento-de-teste=<slug>`**: apaga também o evento de teste do fluxo, com kits, modalidades e cupons — decisão do dono ao deixar a produção limpa antes das provas reais. Sem a opção, o comportamento é o de antes (o evento de teste fica). Slug desconhecido falha sem apagar nada. A limpeza passou a levar os cupons dos eventos apagados junto.
- Roteiro completo no `docs/runbook.md` ("Rodar um comando em produção sem SSH"). 5 testes novos. Suíte: **253 testes, 760 asserções**.

### Cupom no checkout: desconto no valor cobrado e Pix do valor certo (2026-09-20)
- **O atleta informa o cupom ao se inscrever.** Campo opcional no formulário, com **prévia ao vivo**: ao aplicar, um endpoint (`POST /subscribe/event/{id}/cupom`, logado, `throttle:20,1`) valida o código para o kit escolhido e mostra bruto, desconto e total antes do envio — sem criar nada nem gastar uso. O envio refaz todas as checagens: a prévia é conveniência, não autorização.
- **A conta é em centavos inteiros** (`App\Services\PrecoDaInscricao`): `round(preço × 100)`, percentual arredondado meio para cima, teto no próprio valor, líquido por subtração de inteiros. A tela, a coluna `price` e o valor enviado ao Mercado Pago nunca discordam num centavo — 10% de R$ 89,90 é R$ 8,99 e cobra R$ 80,91, não `80.91000000001`. `Coupon::descontoSobre()` passou a delegar para a mesma conta: uma regra só.
- **A inscrição guarda o próprio retrato financeiro**: `list_price` (preço do kit na hora), `discount_amount`, `price` (o cobrado — já existia, nada que lê muda) e `coupon_id`. Gravado na criação, nunca alterado. Um relatório financeiro por evento lê só `subscriptions`, sem reconstruir nada a partir do kit (que muda de preço) ou do cupom (que é editável). Linhas antigas receberam `list_price = price`.
- **O uso é consumido de forma atômica na mesma transação da inscrição** (`Coupon::registrarUso()`, `UPDATE` condicional): se a última vaga foi para outro atleta entre a prévia e o envio, é aí que aparece, e a transação desfaz. Quem já está inscrito é barrado antes de encostar no cupom. **Cancelar não devolve o uso** (decisão do dono): uso consumido é uso gasto, mesmo sem pagamento.
- **Cupom que zera o valor confirma na hora, sem Pix e sem `Payment`.** A confirmação (update condicional `status != 'paid'` → `paid` + `confirmed_at`, e-mail só na primeira vez) saiu do webhook para `App\Services\ConfirmacaoDeInscricao`, que os dois caminhos usam. Efeito colateral bem-vindo: **o webhook passou a gravar `confirmed_at`**, que ficava vazio desde sempre. O e-mail diz "não houve cobrança" no caso gratuito e mostra valor, cupom e desconto no pago.
- **O atleta finalmente vê o valor**: o kit aparece com preço no formulário, o card de "Minhas inscrições" mostra o cobrado com o desconto embaixo (ou "Gratuita"), e a tela do Pix mostra o valor com o cupom. Até aqui o preço não aparecia em lugar nenhum do fluxo.
- **`PixController::generatePix` deixou de aceitar inscrição de outro usuário.** Era um `find($id)` solto: qualquer usuário logado gerava Pix para qualquer inscrição. Agora filtra por `user_id` do logado (404 se não for dele), recusa inscrição já confirmada ou de valor zero, e a rota `/event-pay` ganhou `auth` explícito.
- **Um cupom por inscrição** é estrutural: uma coluna, um campo, e a unique `(event_id, user_id)` impede segunda inscrição no mesmo evento.
- Anotado no backlog: inscrição pendente nunca expira e agora segura o uso de um cupom para sempre; a linha sobre `MERCADOPAGO_TEST_PRICE_ENABLED` estava obsoleta (o `PixAmountResolver` foi removido em 2026-08-29).
- 40 testes novos, entre eles: o Pix gerado com o valor já descontado, o 100% sem `Payment` e com e-mail, o último uso disputado por dois atletas, e o Pix de inscrição alheia dando 404. Suíte: **248 testes, 734 asserções**.

### Cupons de desconto no painel (2026-09-19)
- **Cadastro e gestão de cupons** em `/admin/cupons`: código, desconto (porcentagem ou valor em reais), quantidade de usos, validade e liga/desliga. Até aqui o organizador não tinha **nenhuma** forma de dar desconto — sobravam baixar o preço do kit, que muda o valor para todo mundo, ou acertar por fora e receber o atleta sem pagamento registrado. As duas quebram o caixa e nenhuma é reversível.
- **O cupom pertence a um evento**, não ao organizador (diferente de equipe e patrocinador): desconto é sempre decisão sobre uma prova específica, com preço, data e lotação próprios.
- **Código único por evento, não global.** Unique global acoplaria organizadores diferentes: um passaria a bloquear "CORRE10" para o outro, e o erro contaria que o código existe num lugar que ele não pode ver — o mesmo tipo de vazamento entre inquilinos que o BUG-005 representa. Como a busca do cupom sempre parte do evento, não há ambiguidade a resolver.
- **O contador de uso nunca é campo de formulário** e o incremento é atômico: `UPDATE` condicional (`whereColumn('used_quantity', '<', 'total_quantity')`) em vez de ler-conferir-gravar. Duas inscrições disputando a última vaga — o que acontece de verdade quando um cupom viraliza num grupo de WhatsApp — resolvem-se no banco: a que chega depois afeta 0 linhas e sai com `false`. Ler o saldo antes e gravar depois deixaria uma janela em que o valor já está velho, e o limite estouraria.
- **Três travas passam a valer depois do primeiro uso**: o cupom não pode mais ser apagado (a saída é desativar), e código e evento ficam congelados — trocar o código reescreveria o passado, trocar o evento moveria um desconto concedido para uma prova onde ele nunca valeu. As três vivem no controller, não só na tela.
- **Esgotado ou vencido é estado sem volta**: lê como inativo, o toggle fica desabilitado e o servidor recusa a troca nos dois sentidos. Enquanto não estiver encerrado, o liga/desliga continua livre, mesmo com usos já registrados.
- A quantidade total não desce abaixo do que já foi usado — a listagem mostraria "geradas 1 / utilizadas 3", que não quer dizer nada.
- **Primeira tela do painel com formulário em modal.** Os outros cinco cadastros usam página cheia; cupom é registro curto e de vida curta, e sair da lista para criar um e voltar para criar o próximo seria atrito à toa. Quando o servidor recusa, o modal reabre sozinho com o que foi digitado, na mesma trava de campos.
- O campo de código valida em tempo real (maiúsculas enquanto digita, aviso imediato de formato); a repetição continua sendo conferida no servidor, onde está o banco.
- **Sem `organizer_id` na tabela**: o vínculo com o organizador passa pelo evento, como em `event_kits` e `event_modalities`. E `discount_type` é `string` e não `enum` — dev roda Postgres e produção roda MySQL (ADR 0005), e `enum` vira coisa diferente em cada um.
- A aplicação do cupom na inscrição do atleta **não entrou nesta fatia**: o fluxo de dinheiro (`SubscribeController` → `PixController` → webhook) não foi tocado. A regra de consumo já nasceu pronta e testada (`Coupon::registrarUso()`), então a fatia seguinte é chamá-la. Spec: `docs/specs/cupons-de-desconto.md`.
- 48 testes novos. Suíte: **208 testes, 579 asserções**.

### Botão flutuante de WhatsApp nas páginas públicas (2026-09-01)
- **Botão fixo no canto inferior direito**, em todas as páginas públicas (home, página do evento, minhas inscrições). Abre `wa.me` em aba nova com a mensagem "Olá, vim do site do Corre Virtual" já digitada — serve de rastreio: quem chega por ali se identifica sem precisar perguntar.
- **Número e mensagem em `config/contato.php`** (`WHATSAPP_NUMERO` / `WHATSAPP_MENSAGEM` no `.env`), não na view: número de contato muda (troca de chip, atendimento terceirizado) e isso não é motivo para deploy. **Número vazio esconde o botão** — melhor nenhum botão que um que leva a lugar nenhum.
- O número é limpo (`preg_replace`) antes de virar link: o `wa.me` só aceita dígitos, e "+55 (19) 9…" abre o WhatsApp numa tela de "número inválido" — falha que não aparece em log nenhum, só em reclamação.
- **Ícone é o glifo oficial do WhatsApp inline em SVG**, extraído do Font Awesome 5.11.2 que já está no repo — sem lib nova. Inline e não `<i class="fab">` porque o Font Awesome aqui é a versão JS, que troca o ícone depois que a página pinta: o botão apareceria vazio por um instante em toda visita.
- O rótulo "Fale com a gente" aparece no hover no desktop; no celular fica só o círculo, que é o que as pessoas reconhecem, e onde não existe hover.
- **No mobile da página do evento o botão sobe 84px**: ali o "Inscreva-se" é um CTA fixo na faixa de baixo, e a ação principal da página não pode ficar coberta pelo atendimento. A regra usa `body:has(.cta-button)` porque esse CTA só existe com inscrição aberta — em evento já realizado não há nada embaixo.
- 4 testes novos. Suíte: **160 testes, 424 asserções**.

### Cadastro de patrocinadores no painel, e a seção do site lendo o banco (2026-08-30)
- **CRUD de patrocinadores** em `/admin/patrocinadores`, no molde do de equipes: o patrocinador pertence ao **organizador**, não ao evento — o mesmo apoiador cobre várias provas no ano, e amarrá-lo a um evento obrigaria a recadastrar a cada prova.
- Campos: nome, logo, site, ordem de exibição, observação interna e "aparece no site". A **ordem é do organizador**, não alfabética — quem aparece primeiro é o que foi negociado no contrato.
- **Sem `slug`**, diferente de equipes: patrocinador não tem página nem endereço próprio aqui, seria coluna sem uso.
- O site exige `https://` no campo de site. Sem esquema, "mobspot.com.br" vira link relativo e leva para dentro do painel — parece que funcionou e não funcionou.
- O logo vai para `publico/organizadores/{id}/patrocinadores/{id}/logo.png`, caminho derivado de ids do banco e nunca do nome do arquivo enviado. Termina em `.png` e não `.jpg` como os outros porque logo de marca costuma vir com fundo transparente.
- **A seção de patrocinadores da home passou a ler o banco.** Eram seis SVGs de exemplo ("Logoipsum") colados na view, herdados do template: trocar um exigia mexer em código e subir deploy.
- **Sem nenhum patrocinador cadastrado, a seção não aparece** — fileira vazia embaixo de um título é pior que não ter a seção. Mesma regra da vitrine de realizados.
- Quem tem site vira link, com `rel="noopener"` — sem isso a página aberta ganha acesso a esta pela `window.opener`. Sem logo enviado, aparece o nome em texto, para não abrir buraco na fileira.
- Os logos ficam dessaturados em repouso e coloridos no hover: a fileira para de brigar com a arte dos eventos logo acima.
- **Os seis logos de exemplo foram rasterizados para PNG e migrados para o cadastro**, então nada mudou de lugar no site — e agora o Sidney apaga ou substitui um a um pelo painel, sem deploy. Eles seguem sendo marca fictícia até serem trocados pelos reais; está no backlog.
- 17 testes novos. Suíte: **156 testes, 413 asserções**.

### Rolagem suave, o menu que nunca grudou, e limpeza da base de teste (2026-08-30)
- **Os links do menu deslizam até a seção** em vez de dar um salto seco. Quem pediu menos movimento no sistema operacional (`prefers-reduced-motion`) continua com o salto direto — movimento na tela inteira incomoda de verdade quem tem sensibilidade vestibular.
- **O menu da home nunca grudou**, apesar de estar declarado `position: sticky` desde a Home v2. Duas causas somadas, as duas vindas do CSS do template de painel, que é carregado em toda página pública:
  - `body { overflow-x: hidden }` — `hidden` transforma o elemento num contêiner de rolagem, e isso desliga o `sticky` de quem está dentro. Virou `overflow-x: clip`, que corta igual sem criar contêiner.
  - `html, body { height: 100% }` — prende a caixa do body à altura da janela, e `sticky` só gruda enquanto essa caixa está na tela. Virou `height: auto; min-height: 100%`.
- A margem que impede o título da seção de parar atrás do menu vem da altura medida do próprio menu (`--cv-nav-altura`, no `home-v2.js`): as duas barras mudam de tamanho conforme a largura, e um número fixo no CSS erraria em algum lugar.
- `global.css`, `top-bar.css` e `forms.css` ganharam cache-busting — sem isso a correção só apareceria para quem limpasse o cache. É a quarta vez que isso morde neste projeto.
- **`php artisan base:limpar-testes`**: tira da base o que sobrou da fase de demonstração. Até hoje nada no banco era prova real — eventos de seeder, kits todos a R$ 0,05, inscrições feitas por quem estava testando. Aparecia em "minhas inscrições" como se fosse compromisso do atleta, apontando para evento que já tinha saído do site.
- O comando apaga os seis eventos mocados com suas modalidades e kits, e todo o histórico de inscrição e pagamento. Ficam os atletas, os eventos reais, o evento de teste do fluxo e as modalidades e kits deles. Os eventos são identificados por **slug**, não por id: os ids são diferentes em dev e produção, e um número errado apagaria a prova errada.
- Simula por padrão. Só apaga com `--force`, e dentro de uma transação — a ordem importa (pagamento aponta para inscrição, que aponta para kit, modalidade e evento), e uma falha no meio deixaria referência órfã.
- 5 testes novos, mais as factories de inscrição e pagamento que faltavam. Suíte: **139 testes, 358 asserções**.

### Vitrine de eventos realizados, topo do evento e prévia do link com a arte (2026-08-30)
- **A home mudou de ordem**: banner → próximos eventos → **patrocinadores** → **eventos realizados** → sobre nós. A seção de patrocinadores era a última da página, depois do "sobre nós" — quem paga para aparecer aparecia onde ninguém mais estava rolando.
- **"Eventos realizados" virou vitrine**: só os cartazes, sem link e sem botão. A prova acabou, não há o que fazer com ela. Seis por linha no desktop, três no tablet, dois no celular.
- **As nove artes do site antigo entraram na vitrine** (`correvirtual.com.br`, seção "Eventos Encerrados"): Desafio de Inverno, Arraiá do Corre, Sacra Run, Corre pela Conscientização do Autismo, CarnaRun do Quarteto, Corre Solidário de Natal, Mega Gelo & Chopp, Corra que a Bruxa Vem Aí e Pastelícia. Recomprimidas de PNG para JPEG: 2,5 MB viraram 959 KB.
- A vitrine junta duas fontes que o visitante não distingue: essas artes avulsas (`config/galeria.php`, por organizador) e os eventos cadastrados aqui que já passaram da data. Sem a segunda, uma prova cadastrada sumiria do site no dia seguinte à realização.
- **O topo da página do evento virou degradê no azul do tema com o nome em texto grande**, com a mesma altura para todo evento. Ele tentava encaixar a arte, e a arte é retrato: recortada sumia o nome e a data, que ficam no alto do cartaz; inteira virava um cartaz minúsculo entre duas faixas. A arte não se perde — é o cartaz da home e é o que viaja no compartilhamento.
- **O compartilhamento passou a levar a arte de verdade.** A imagem estava declarada, mas era o cartaz retrato de até 1,9 MB: o robô do WhatsApp monta um cartão deitado (recortava o meio) e desiste da prévia bem antes daquele peso — na prática o link chegava sem imagem. Agora existe uma derivada de **1200x630** com a arte inteira sobre uma versão desfocada dela mesma, entre 56 e 82 KB.
- O desfoque é feito numa miniatura de 60x32 e ampliado: o filtro do GD é fraco e caro, e ampliado o borrão sai mais suave do que aplicado na imagem inteira.
- Organizador e plataforma também ganharam `og.jpg` de 1200x630 — é isso que autoriza declarar `og:image:width`/`og:image:height`, que fazem o cartão sair grande já na primeira leitura.
- Nova arte no painel gera a derivada na hora, dentro de `try/catch`: a arte já foi salva, e falha aqui não pode derrubar o cadastro do evento. Para o que já existia, `php artisan og:gerar`.
- **"Minhas inscrições" mostra a arte inteira**, mantendo o card deitado. No celular a coluna da arte ganha altura para o cartaz caber em pé.
- `my-subscriptions.css` ganhou cache-busting (`?v=filemtime`), que faltava.
- O `<h2>` que repetia o nome do evento logo abaixo do topo saiu — com o nome grande no degradê, era a mesma frase duas vezes seguidas. O título duplicado dentro da seção de patrocinadores também.
- **Limpeza dos eventos mocados**: CarnaRun 2025, Corre que a Bruxa 2025 e Pastelícia saíram do site. Foram **desativados, não apagados** — seguram 8 inscrições pagas com pagamento aprovado no Mercado Pago, de três pessoas reais, e apagar o evento levaria esse histórico junto. Os eventos do organizador 2 (Borafitness) não foram tocados: são o catálogo de outro inquilino.
- O evento de teste do fluxo (R$ 0,05) voltou a aparecer, por último na lista.
- 14 testes novos. Suíte: **134 testes, 340 asserções**.

### Home separada em próximos e realizados, e cor nos ícones do painel (2026-08-29)
- **A home passou a ter duas seções**: "Próximos eventos", do mais perto para o mais longe (é onde o atleta se inscreve), e "Eventos realizados", do mais recente para o mais antigo (é histórico). Antes era uma lista só, com o passado misturado no meio.
- Sem nenhuma prova futura, a seção explica isso em vez de aparecer vazia.
- As artes dos eventos passados ficam em **cor cheia** de propósito: é o que mostra a qualidade do trabalho do organizador para quem está conhecendo a página agora. Quem separa as duas coisas é o título da seção.
- **Cor nos ícones do painel**, uma por área (azul o painel, verde eventos, âmbar modalidades, roxo kits, magenta equipes) — num painel de poucas áreas a cor vira atalho de leitura, e a pessoa para de ler o texto do menu. Continua sendo a biblioteca Feather que já estava no template; o que faltava era cor, não ícone novo.
- Os seletores precisaram ser tão específicos quanto os do SB Admin Pro (`.sidenav .sidenav-menu .nav .nav-link .nav-link-icon .feather`), senão o cinza dele vencia e nada mudava.

### Correção: aplicação travava com "Operation not permitted" (2026-08-29)
- `VIEW_COMPILED_PATH=/tmp` foi removido. O `/tmp` é compartilhado e tem sticky bit: um arquivo de view compilada que nasceu de outro usuário (qualquer comando rodado como root no container) trava a aplicação **inteira** com erro 500 e `rename: Operation not permitted`. Aconteceu três vezes durante o desenvolvimento.
- Agora vale o padrão do Laravel (`storage/framework/views`), que é volume próprio do container. O deploy remove a linha do `.env` gerado a partir do secret, já que ela ainda vive lá.
- `event-cards.css` ganhou cache-busting (`?v=filemtime`). Sem isso, mudança de estilo só aparece para quem limpa o cache — a Home v2 já tinha esse cuidado, essa folha não.
- 6 testes novos para a separação da home. Suíte: **105 testes, 251 asserções**.

### Prévia bonita do link ao compartilhar (2026-08-29)
- **Open Graph e Twitter Card no site e no painel.** Sem isso o endereço chega no WhatsApp como texto cru, sem imagem nem descrição — impressão exatamente errada para uma plataforma que cobra dinheiro.
- **A página de um evento fala do evento**: título com a data, local e descrição, e a imagem do próprio evento. A home usa o nome e o banner do organizador. Sem imagem específica, cai no banner padrão — cartão sem imagem nenhuma é bem pior que um genérico.
- A imagem do cartão é sempre **URL absoluta**: quem monta a prévia é um servidor de fora, que não resolve caminho relativo. Tem teste para isso.
- O painel declara `noindex, nofollow` e um cartão que diz ser área restrita, sem descrever conteúdo — quem receber o endereço vê que não é parte do site do organizador.
- **A tag de charset subiu para a primeira linha do cabeçalho.** Estava depois de todas as outras meta tags, e os robôs que montam a prévia leem só o começo do documento. A meta `description`, que estava vazia, passou a ser preenchida.
- `<x-app.head>` agora aceita `:og` — componente Blade não herda variável da view como `@include` faz, e era por isso que os dados do evento não chegavam ao cabeçalho.
- 5 testes novos. Suíte: **99 testes, 240 asserções**.

### Brasão da equipe e correção do atalho para o site (2026-08-29)
- **A equipe pode ter brasão.** Upload no cadastro, direto para o R2 (`publico/organizadores/{id}/equipes/{id}/brasao.jpg`). Na listagem ele aparece **redondo, antes do nome**; equipe sem brasão mostra as iniciais no mesmo tamanho e formato, para a coluna não ficar desalinhada.
- Coluna `teams.has_logo`: com o CDN não dá para perguntar ao disco se o arquivo existe (seria uma requisição de rede por linha da listagem), então quem responde é o banco. Mesma escolha já feita para a imagem do evento.
- `App\Support\ImagemPublica` extraído: gravar, apagar e validar imagem no bucket viraram um lugar só, usado pelo evento e pela equipe. O que é específico de cada um é o caminho — e ele sempre sai de ids do banco, nunca do nome do arquivo enviado.
- **O atalho "ver o site público" ia parar no próprio painel.** Ele usava `url('/')`, que em `admin.correvirtual.com.br` devolve a raiz do domínio do painel — e o nginx manda essa raiz de volta para `/admin`. Agora o endereço sai do domínio do organizador (`Organizer::siteUrl()`), com uma exceção para o ambiente local: lá o domínio gravado no banco é o de produção, e mandar quem está testando para lá é convite a mexer no site errado.
- 7 testes novos. Suíte: **94 testes, 224 asserções**.

### Evento já realizado vira somente leitura, e selo Mobspot (2026-08-29)
- **Prova que já aconteceu não recebe mais alteração de modalidade nem de kit.** Mexer depois da corrida bagunça o histórico de quem se inscreveu e não muda nada no mundo real. A listagem continua abrindo (vira só leitura, com um aviso e sem os botões), e o evento sai do seletor de "cadastrar em" das telas gerais. A checagem vive no controller, não só na tela: esconder no front não é proteger, e o valor do `<select>` é entrada do usuário como qualquer outra.
- **Ícone do perfil na barra de cima estava invisível** — a classe `btn-transparent-dark` do template pinta o ícone da própria cor do fundo escuro. Mesmo problema que o botão do menu tinha; agora os dois usam a mesma regra e ficam brancos.
- **"Ver o site público" saiu do rodapé e virou uma casinha na barra**, à esquerda do perfil — no rodapé ninguém rolava até lá.
- **Selo "Desenvolvido por Mobspot"** no rodapé do site público e no do painel, com UTM próprio de cada um. Discreto: menor e mais apagado que o resto, sem competir com o conteúdo do organizador.
- No menu lateral do painel, "Organizador / Corre Virtual Eventos" deu lugar a "Desenvolvido por: Mobspot" — o nome do organizador já aparece na barra de cima, ao lado da logo, e repetir gastava o único espaço fixo do menu.
- 6 testes novos para a regra de evento realizado. Suíte: **87 testes, 205 asserções**.

### Ajustes no painel pedidos pelo Sidney (2026-08-29)
- **"Categoria" virou "modalidade" em todo o painel** — rota, nome de rota, rótulos e testes. É o nome correto do domínio e o que o banco já usava (`EventModality`); a tela é que estava errada.
- **Modalidades e Kits ganharam entrada no menu lateral**, com listagem de todos os eventos do organizador (coluna do evento junto) e um seletor "cadastrar em [evento]" — modalidade e kit vivem dentro de um evento, então o atalho pergunta em qual antes de abrir o formulário aninhado de sempre. O evento escolhido é validado contra os do organizador: o valor vem de um `<select>`, que é entrada do usuário como qualquer outra.
- **Botão do menu no celular**: vinha do template com a cor do próprio fundo escuro (`btn-transparent-dark`) e menor que o texto ao lado — praticamente invisível, justo no tamanho de tela em que ele é a única forma de abrir o menu. Agora é branco, do corpo do nome, e fica colado à marca (antes o `order-1` o jogava para o canto oposto da barra). O nome do organizador também passou a aparecer no celular (era `d-none d-sm-block`), agora com a logo dele ao lado.
- **Os 4 cards do painel saíram de dentro do banner.** O SB Admin Pro combina `pb-10` no cabeçalho com `mt-n10` no conteúdo para o primeiro bloco invadir o banner de propósito. Ficava estranho com os cards, então a sobreposição foi desfeita: banner mais baixo (`pb-4`) e conteúdo começando logo abaixo dele.
- 4 testes novos para as telas gerais e o atalho de cadastro. Suíte: **81 testes, 188 asserções**.

### Painel — fatia 2: categorias, kits, equipes e upload de imagem (2026-08-29)
- **CRUD de categorias** (as distâncias) e de **kits**, aninhados no evento: as rotas são `/admin/eventos/{id}/categorias` e `/admin/eventos/{id}/kits`, o que torna impossível cadastrar um kit sem dizer de qual evento é. Abas no topo ligam as três telas do mesmo evento.
- **CRUD de equipes** (`/admin/equipes`), por organizador e não por evento — a mesma assessoria corre vários eventos no ano. Cada equipe é **aberta** (aparecerá para o atleta escolher) ou **fechada** (o vínculo é decidido pelo organizador). Tabela `teams` nova, com slug único por organizador. **A escolha na inscrição do atleta não foi tocada**, conforme combinado.
- **Upload das imagens do evento** (banner e card, separados) direto para o R2, não para o container. O caminho é derivado do organizador e do evento — nunca do nome do arquivo enviado, que é entrada do usuário. Apagar o evento leva as imagens junto, senão um evento futuro com o mesmo id herdaria a imagem deste.
- **Situação do evento deduzida das datas** (decisão do dono): Realizado / Inscrições abertas / Inscrições encerradas / Inativo, sem coluna nova no banco. Os 6 eventos antigos aparecem como Realizado.
- **Evento de teste novo** ("Corrida de Teste do Fluxo 2026") com 3 categorias e 2 kits a R$ 0,05, para testar inscrição e pagamento de ponta a ponta.
- Cabeçalho do painel passou a usar a foto da ponte de Mogi Guaçu, a mesma do banner do site — com degradê por cima para o texto continuar legível.
- Categorias e kits com inscritos não podem ser apagados (a foreign key é `restrictOnDelete`; melhor explicar do que deixar estourar erro 500).
- Kit com preço R$ 0,00 é recusado no cadastro: o Mercado Pago não aceita cobrança zerada, e descobrir isso na hora de gerar o Pix seria pior.
- **26 testes novos**, incluindo o isolamento de categoria e kit — que não têm `organizer_id` próprio e dependem do evento, uma camada a mais onde o escopo pode escapar. Suíte: **77 testes, 176 asserções**.

### Três armadilhas de infraestrutura encontradas nesta rodada
- **`vendor/` não está no git e o deploy nunca rodou `composer install`.** O vendor de produção era um instalado à mão uma única vez; qualquer dependência nova quebraria o site com "class not found", e só na hora que a rota fosse usada. Descoberto ao adicionar o driver de S3. Corrigido no workflow.
- **GD não estava no container.** Sem ele o Laravel nem consegue gerar imagem de teste ("GD extension is not installed"), e qualquer tratamento futuro de imagem dependeria dele. Entrou no `Dockerfile`.
- **nginx cortava upload em 1 MB** (`client_max_body_size` padrão), devolvendo 413 antes do Laravel ver a requisição — a validação de 5 MB da aplicação nunca chegava a rodar. O PHP também cortava em 2 MB. Os dois limites agora batem (16 MB no nginx, 6 MB por arquivo no PHP). Achado testando o upload no navegador de verdade.

### Produção fora do ar por 9 dias — religada e protegida (2026-08-29)
- **`eventos.correvirtual.com.br` ficou fora do ar de 20/08 a 29/08.** Causa: a VPS reiniciou sozinha em 20/08 05:24 (atualização de kernel, `6.8.0-111` → `6.8.0-136`) e os containers não voltaram, porque subiam com `RestartPolicy=no`. Nenhum dado foi perdido — o banco fica na Hostgator, fora da VPS.
- Containers religados e com `restart=unless-stopped` aplicado (`docker update`), então o próximo reboot traz o site de volta sozinho. **Falta ainda** levar o `restart: unless-stopped` para o `docker-compose.yml` do repositório, senão um `up -d --build` do deploy recria os containers sem a política.

### Rotina de backup do banco (2026-08-29)
- **`/usr/local/bin/corre-backup.sh` na VPS**, agendado no cron para 03:20 todo dia. Faz `mysqldump` de `webcit29_eventos_prod` e `webcit29_eventos_dev`, comprime, e guarda em `/opt/backups/corre/` com retenção de 14 dias. Resolve a pendência aberta desde 2026-08-02 (ADR 0005).
- Roda na VPS, que é máquina diferente do banco (Hostgator) — a cópia já nasce fora do servidor de origem.
- **Valida antes de aceitar**: o dump só vira backup se tiver mais de 1KB e contiver `CREATE TABLE`; senão vira `.SUSPEITO` e a rotação é suspensa. Um dump ruim nunca sobrescreve um backup bom — foi por falta disso que este projeto já perdeu um banco inteiro.
- **Testado restaurando de verdade**, não só rodando: o dump de produção foi restaurado num MySQL 5.7 temporário e conferido linha a linha (2 organizadores, 6 eventos, 17 modalidades, 9 kits, 4 usuários, 8 inscrições, 7 pagamentos — bate com produção).
- Credenciais lidas do `.env` da aplicação, nunca escritas no script; `MYSQL_PWD` evita a senha aparecer na lista de processos do servidor.

### Arquivos saem do container: bucket R2 criado e populado (2026-08-29)
- **Bucket `correvirtual-arquivos`** criado na conta Cloudflare R2 já usada pelo Cubo. Os outros quatro buckets da conta (`cubo-arquivos`, `cubo-backups`, `mia-documentos`, `mobspot-backups`) são de outros projetos e **não foram tocados** — contagem conferida antes e depois.
- Estrutura com `publico/` e `privado/` separados na raiz, e `organizer_id` como primeiro segmento dentro de cada um (isolamento entre organizadores é o ponto fraco conhecido do projeto — BUG-005). Detalhes e o porquê de cada escolha em `docs/specs/armazenamento-r2.md`.
- **Os 18 arquivos de `src/public/images/` foram copiados** (6,03 MB) com `Content-Type` e `Cache-Control: max-age=31536000, immutable`. Download conferido byte a byte.
- **Nada foi apagado e nenhum código foi alterado** — o site continua servindo tudo do disco local, exatamente como antes. A cópia no R2 está parada esperando a migração de código.
- Levantado no processo: três views (`event-card`, `my-subscriptions`, `main-banner`) decidem se mostram imagem ou fallback com `file_exists(public_path(...))`. Com o arquivo no R2 isso responde `false` sempre e **todo mundo cai no fallback em silêncio** — é o bloqueio real da migração, e o motivo de ela exigir mudança de código e não só de configuração.

### Deploy estava meio quebrado em silêncio (2026-08-29)
- O `set -euo pipefail` acrescentado ao workflow expôs, no primeiro deploy, uma falha que já existia: **`docker-compose: command not found`**. A VPS só tem `docker compose` (v2, plugin); o script chamava `docker-compose` (v1, binário), que nunca existiu lá.
- Sem `set -e`, o erro não interrompia nada — o script seguia para `migrate` e `optimize` e imprimia "✅ Deploy finalizado com sucesso!". **O container nunca era reconstruído em nenhum deploy.** Passava despercebido porque `./src` é bind mount e o código PHP atualiza sozinho; só mudança de imagem (extensão nova no Dockerfile, por exemplo) é que teria sumido sem aviso.
- Corrigido para `docker compose up -d --build`.

### CDN no ar e painel com domínio próprio (2026-08-29)
- **`https://cdncorrevirtual.mobspot.com.br`** ligado ao bucket `correvirtual-arquivos`, certificado da Cloudflare ativo, cache confirmado (`cf-cache-status: HIT`). O site local já serve todas as imagens de lá — validado no navegador. `cdn.correvirtual.com.br` não era possível: domínio próprio no R2 exige a zona na Cloudflare, e `correvirtual.com.br` está na WebCit.
- **🔴 Corrigido um erro de desenho do dia anterior: `publico/` e `privado/` como prefixos do mesmo bucket não protegiam nada.** Um domínio público do R2 expõe o **bucket inteiro** — com o domínio ligado, `https://<cdn>/privado/...` respondeu **200**. Prefixo não é fronteira de segurança. Nada vazou (só havia marcadores vazios), mas documento de atleta ali seria legível por qualquer um. Agora são **dois buckets**: `correvirtual-arquivos` (com domínio) e `correvirtual-privado` (sem domínio nenhum, só com credencial). O prefixo `privado/` foi apagado do bucket público e agora dá 404.
- **`admin.correvirtual.com.br` no ar**: DNS apontado pelo Sidney, certificado Let's Encrypt emitido (vence 2026-11-27, renovação automática) e bloco próprio no nginx, com a raiz do domínio redirecionando para `/admin`. O painel em si só aparece quando o código subir — produção ainda roda `182c56a`.
- `docker/nginx/default.conf`: removido o `server_name` `129.121.37.184`, IP de um VPS anterior que não existe mais (item do backlog).
- Achado operacional: bind mount de **arquivo** no Docker prende no inode — trocar o arquivo no host com `mv` não chega no container, que continua lendo o antigo. `nginx -t` passa e o reload não muda nada. Exige recriar o container.
- Achado operacional: `docker compose up -d --force-recreate` **zera a política de restart** aplicada por `docker update`, porque ela não está no `docker-compose.yml`. Reaplicada nos dois containers; enquanto não for para o repositório, todo recreate a perde.

### Imagens centralizadas — virar o CDN passa a ser uma variável (2026-08-29)
- **`src/public/images/` reorganizado para espelhar o bucket R2 exatamente** (`organizadores/{id}/eventos/{id}/card.jpg`, `plataforma/padrao/…`, `plataforma/home/…`). Os dois lados têm a mesma árvore — é isso que torna a virada de chave uma variável em vez de uma refatoração.
- **`App\Support\Arquivos`** vira o único lugar que monta URL de imagem. A regra estava copiada em **seis** views (`event-card`, `my-subscriptions`, `main-banner`, `banner-v2`, `event-details`, `top-bar`), com variações entre elas.
- **`config/arquivos.php` + `ARQUIVOS_BASE_URL`** (documentado no `.env.example`): vazio serve do disco do container, preenchido serve do CDN.
- **A existência da imagem de evento passa a ser decidida pelo banco** (`banner_url` preenchido), não por `file_exists` no disco, com `onerror` no `<img>` como rede de segurança. Sem isso, servir do R2 jogaria **toda** imagem no fallback silenciosamente.
- **DEBT-009 corrigido de passagem**: o favicon montava `images/organizers/{id}logo.png` — sem a barra antes de `logo.png`, dava 404 em todas as páginas do site desde sempre.
- `images:generate-gemini` passou a gravar em `public/images/plataforma/home`.
- **8 testes novos**, incluindo um que varre as views e falha se alguém voltar a montar caminho na mão (`file_exists(public_path(...))` ou `asset('images/...')`) — sem ele a próxima view fora do padrão quebraria o CDN sem avisar. Suíte: **51 testes, 113 asserções**.
- Validado no navegador: home (banner + cards) e página de evento renderizando com os caminhos novos.
- **Nada foi virado ainda**: `ARQUIVOS_BASE_URL` está vazio em todo lugar, tudo continua vindo do disco. Falta só decidir o domínio do CDN.

### Painel administrativo — fatia 1: esqueleto e cadastro de eventos (2026-08-29)
- **Decisão registrada em `docs/decisoes/0006-painel-admin-neste-projeto.md`**: o painel é construído neste projeto, não migra para o Cubo. Spec completo em `docs/specs/painel-admin.md`.
- **Entrada do painel** em `/admin`, com duas travas: `auth` + `EnsureOrganizerAdmin` (exige papel `organizer_admin` **e** `organizer_id` preenchido). O papel já existia no enum de `users.role` desde a migration original e nunca tinha sido usado.
- Dentro do painel o escopo vem do **usuário logado**, não do domínio — diferente do site público (ADR 0002). `IdentifyOrganizerByDomain` ganhou uma exceção para as rotas do painel e do login: sem ela, `admin.correvirtual.com.br`, que não pertence a organizador nenhum, cairia no 404 de "organizador não encontrado".
- **`php artisan admin:criar {email}`**: cria um administrador novo ou promove um atleta existente. Não existe tela para isso (não há `super_admin` implementado).
- **CRUD de eventos** (`/admin/eventos`): listar com contagem de categorias/kits/inscritos, criar, editar e apagar. Toda busca de registro específico filtra por organizador na própria consulta e devolve **404** (não 403) quando não é do organizador — assim um organizador não descobre nem que o registro do outro existe.
- Apagar evento que já tem inscrição é bloqueado (cascatearia para inscrição paga); a orientação na tela é desativar.
- Layout `layouts/admin.blade.php` a partir do SB Admin Pro (`TEMPLATES/Painel-Admin/`), recolorido para a paleta do projeto. Assets em `public/assets/admin/` — **não** em `public/admin/`, que faria o nginx (`try_files $uri $uri/`) achar o diretório e devolver 403 na rota do painel.
- `OrganizerFactory` criada — `EventFactory` já chamava `Organizer::factory()` desde sempre, e nunca tinha quebrado porque os testes montavam organizador na mão.
- **19 testes novos** (`tests/Feature/Admin/`), incluindo os de isolamento entre organizadores: admin de A recebe 404 ao abrir, alterar ou apagar evento de B, e o registro de B fica intacto. Suíte total: 43 testes, 101 asserções.
- Validado no navegador via Playwright: login, painel, criação de evento e listagem.

### Desenvolvimento local ~70× mais rápido (2026-08-29)
- `vendor/` passou a viver num volume nomeado do Docker no ambiente local (`docker-compose.local.yml`, que produção nunca lê). O boot do Laravel abria ~10 mil arquivos pelo bind mount do Windows a cada request: a rota `/up`, que não faz nada, levava **11,8s**; agora leva **0,17s**. A home caiu de 13,4s para 1,1s.
- Consequência: depois de mexer no `composer.json`, rode `docker exec corre_app composer install` para atualizar o `vendor/` de dentro do container. O do host continua servindo o autocomplete da IDE.

### E-mail de confirmação de inscrição (2026-08-03)
- **`App\Mail\SubscriptionConfirmed`** (novo): enviado quando o webhook do Mercado Pago confirma o pagamento — traz evento, data, local, modalidade, kit, botão "Ver minha inscrição" e botão "Adicionar à agenda" (link pro Google Calendar como evento de dia inteiro, sem inventar horário de término).
- **`MercadoPagoWebhookController::handle()`**: o update de `Subscription.status` pra `paid` agora é atômico e condicional (`where('status', '!=', 'paid')`) — evita e-mail duplicado se o Mercado Pago reenviar a notificação (retry). O e-mail só é enviado quando esse update afeta 1 linha.
- Envio via `dispatch(fn () => ...)->afterResponse()` — roda depois da resposta HTTP já ter sido enviada ao Mercado Pago (hook `terminate()` do Laravel, funciona com PHP-FPM), sem precisar de worker de fila (não há um rodando neste projeto — ver DEBT-010). Evita segurar o webhook esperando o SMTP, que é lento.
- Testes novos em `tests/Feature/MercadoPagoWebhookControllerTest.php`: webhook aprovado envia o e-mail certo (`Mail::fake()` + `Mockery::mock('alias:...')` no `MercadoPagoService::getPayment`); retry pra inscrição já paga não reenvia.
- `docs/specs/pagamentos-pix.md` atualizado com o fluxo completo.

### Fase de teste em produção (2026-08-02/03)
- **Preço de teste temporário (R$0,05)**: `PixAmountResolver` sobrepõe o valor cobrado no Pix pra qualquer evento/kit enquanto `MERCADOPAGO_TEST_PRICE_ENABLED=true` — decisão do Sidney pra encher a plataforma de testes sem cobrar valor cheio. Não mexe em `Subscription::price` (continua o preço real do kit). Reverter é só trocar a env var pra `false`. Ver `docs/backlog.md`.
- **Credencial Mercado Pago trocada pra conta do Uéslei** (era a do Sidney) — a antiga ficou comentada em `src/.env`, não apagada.
- **DEBT-005 corrigido**: `MercadoPagoService::createPixPayment` não faz mais `dd()` quando a API do Mercado Pago falha — loga o erro e devolve `null`; `PixController::generatePix` mostra "Estamos com instabilidade no pagamento no momento. Tente novamente mais tarde." em vez de derrubar a request com uma tela de debug. Achado ao vivo testando com a credencial nova (ver BUG-008 no backlog — a conta do Uéslei ainda não está liberada pro Mercado Pago processar pagamentos live).

### Infraestrutura de produção (2026-08-02) — site no ar
- **`https://eventos.correvirtual.com.br` está em produção.** VPS (Hostgator, `143.95.218.62`) provisionada do zero: Docker + Docker Compose instalados, repositório clonado, `.env` de produção configurado (banco, Mercado Pago real, `APP_DEBUG=false`, `APP_KEY` novo), certificado TLS real emitido via certbot (Let's Encrypt, renovação automática agendada, expira 2026-10-31).
- **Decisão**: banco de produção e desenvolvimento migram pra MySQL gerenciado na Hostgator (nada de banco local) — ver `docs/decisoes/0005-banco-producao-hostgator-mysql.md`. Migrations + seed rodados com sucesso em `webcit29_eventos_prod` e `webcit29_eventos_dev`.
- `docker/php/Dockerfile`: adiciona `pdo_mysql` (mantém `pdo_pgsql` por enquanto).
- Secrets do GitHub Actions (`HOST`, `PORT`, `USERNAME`, `PASSWORD`, `APP_ENV`) atualizados — o deploy automático (`.github/workflows/deploy.yml`, dispara em push pra `main`) está funcional pra próximas atualizações.
- Fluxo completo validado via Playwright contra o ambiente real (banco remoto): cadastro → verificação de e-mail → login automático → escolha de evento/modalidade/kit → inscrição criada com o preço correto do kit (R$59,90, não o antigo valor fixo). Não testado o passo de gerar o Pix em si, de propósito — evitar chamar a API real do Mercado Pago numa sessão de teste.
- Único pendente conhecido: `MERCADOPAGO_WEBHOOK_SECRET` de produção ainda não configurado — ver "Known issues".

### Home v2
- Redesign da Home (`/`): menu de duas camadas (barra utilitária + navegação principal, sticky) e banner rotativo com CTAs, inspirados em `TEMPLATES/Front-End/` e recoloridos pra azul escuro/claro (`--cv-navy` `#0d1b2a` + `--cv-blue` `#1a71b2`, já usados no projeto). Detalhes em `docs/specs/frontend-publico.md`.
- Novos arquivos: `layouts/app-v2.blade.php`, `components/app/nav-v2.blade.php`, `components/app/banner-v2.blade.php`, `public/css/home-v2.css`, `public/js/home-v2.js` (vanilla, sem jQuery/Bootstrap/Swiper novos). Só `index.blade.php` usa o layout novo — todas as outras páginas continuam em `layouts/app.blade.php`, intocado.
- `php artisan images:generate-gemini`: gera as imagens do banner via Gemini (offline, uma vez só — nunca em runtime). Executado com sucesso — `public/images/home-v2/banner-{1,2,3}.jpg` gerados e já usados nos slides 2 e 3 do banner (slide 1 continua priorizando o banner real do organizador quando existe).
- Ajuste após 1ª revisão visual do organizador: nome do organizador e botões de autenticação estavam duplicados nas duas barras do menu — barra utilitária virou só tagline; toda a autenticação (Entrar/Criar conta/Minhas inscrições/Sair) passou a viver só na barra principal, que trocou o fundo branco por um tom azul claro (`--cv-blue-pale`) pra combinar com o resto da paleta.
- Ajuste após 2ª revisão: banner-1-organizer-cropped.jpg — recorte do banner real do organizador removendo o bloco de logo, mantendo a ponte de Mogi Guaçu, que passou a ser o slide 1 do banner (no lugar da imagem crua com a logo). Ícones de rede social na barra utilitária removidos (usuário reportava desalinhamento não reproduzível mesmo após limpar cache; não foi encontrada a causa — removidos por segurança em vez de continuar investigando). `layouts/app-v2.blade.php` ganhou cache-busting (`?v={{ filemtime(...) }}`) no CSS/JS da v2, pra evitar esse tipo de divergência "funciona aqui, não funciona aí" de novo.
- Corrigido de passagem (achado testando a v2, afeta o site todo): `.block-header-title` sem `flex-wrap` estourava a largura da tela em mobile; `--navy` era usada em `global.css` mas nunca definida.

### Fixed
- **BUG-001**: `SubscribeController::subscribe()` gravava `price => 0.05` fixo em toda inscrição, ignorando o preço do `EventKit` escolhido — agora usa `$kit->price`. Teste: `tests/Feature/SubscribeControllerTest.php`.
- **BUG-002**: `subscriptions.modality_id`/`kit_id` agora são foreign keys de verdade (`event_modalities`/`event_kits`, com `restrictOnDelete()`), e `SubscribeController::subscribe()` valida que a modalidade/kit escolhido pertence ao evento antes de criar a inscrição.
- **BUG-003**: removida a comparação `status !== 'canceled'` (nunca era verdadeira) e o branch morto de "reativar inscrição cancelada" em `SubscribeController::subscribe()` — cancelar continua apagando a linha (`SubscribeController::cancel()`), então uma inscrição encontrada só pode estar `pending` ou `paid`.
- **BUG-004**: `POST /api/webhooks/mercadopago` agora valida a assinatura HMAC-SHA256 do Mercado Pago (`App\Services\MercadoPagoWebhookSignature`) antes de processar qualquer notificação; rejeita com `401` se a assinatura for inválida ou `MERCADOPAGO_WEBHOOK_SECRET` não estiver configurado.
- `tests/Feature/ExampleTest.php`: religado `RefreshDatabase` (estava comentado, migrations nunca rodavam no sqlite em memória).

### Added
- Testes automatizados para os três fixes acima: `tests/Feature/SubscribeControllerTest.php`, `tests/Unit/MercadoPagoWebhookSignatureTest.php`, `tests/Feature/MercadoPagoWebhookControllerTest.php`.
- `MERCADOPAGO_WEBHOOK_SECRET` em `src/config/services.php` e `src/.env.example`.
- Documentação viva do projeto: `docs/visao-geral.md`, `docs/arquitetura.md`, `docs/runbook.md`, `docs/backlog.md`, ADRs em `docs/decisoes/` e specs baseline em `docs/specs/`.
- `CLAUDE.md` com as regras de trabalho (SDD, plano antes de código, testes obrigatórios).
- Serviço Postgres 16 no `docker-compose.yml` (o banco anterior, hospedado em outro provedor, foi perdido).
- Pasta `TEMPLATES/` documentada (`TEMPLATES/README.md`) — já recebeu o template do site público em `TEMPLATES/Front-End/`.
- `docker/nginx/local.conf` + `docker-compose.local.yml`: overlay opcional só para dev local (HTTP puro, sem depender dos certificados Let's Encrypt de produção). Produção continua usando `docker-compose.yml` sozinho, como já era.

### Changed
- `docker/php/Dockerfile` passou a instalar `pdo_pgsql` em vez de `pdo_mysql`.
- `src/.env.example` atualizado com as variáveis do Postgres e `MERCADOPAGO_ACCESS_TOKEN` (antes ausente).
- `.github/workflows/deploy.yml` agora sincroniza automaticamente as credenciais do Postgres a partir do `.env` do Laravel a cada deploy.
- `reset-dev.sh` passou a aguardar o healthcheck do Postgres (`corre_db`) em vez do MySQL.

### Verificado nesta rodada
Stack local validada de ponta a ponta com os containers reais (`docker compose up -d --build`): migrations rodam limpas em Postgres 16, seeders populam 2 organizadores / 6 eventos / 17 modalidades / 9 kits, e a home carrega os eventos via nginx (`http://localhost`, HTTP 200). Detalhes e comandos em `docs/runbook.md`.

### Known issues
Ver `docs/backlog.md` para a lista completa. BUG-001 a BUG-004 corrigidos; seguem abertos BUG-005 (sem validação de prazo/capacidade/tenant na inscrição) e BUG-006 (`organizer_id` não preenchido no cadastro). Do primeiro deploy de produção (2026-08-02): `MERCADOPAGO_WEBHOOK_SECRET` ainda não configurado (webhook do Pix rejeita tudo até isso ser feito — único bloqueador pra pagamento funcionar de ponta a ponta); rotina de backup do banco de produção ainda não definida.
