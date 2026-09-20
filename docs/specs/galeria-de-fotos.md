# Galeria de fotos da home

Status: Implementado (2026-09-20)

## Problema

A home mostra o que vem (próximos eventos), quem apoia (patrocinadores) e o
que já foi entregue (vitrine de realizados) — mas não mostra **gente correndo**.
Foto de prova é o que convence quem chegou pela primeira vez: largada cheia,
medalha no pescoço, o pastel depois. O dono pediu uma faixa no estilo do feed
do Instagram, logo depois de "Próximos eventos": 100% da largura, fotos
quadradas, 6 colunas × 2 linhas no desktop e 2 colunas × 3 linhas no celular.

A opção de puxar do Instagram automaticamente (API oficial, conta profissional,
token renovável) foi apresentada e **descartada** por ele nesta rodada: a galeria
é manual, alimentada pelo painel. Nada impede o Instagram virar uma fonte
depois — a faixa não sabe de onde a foto veio.

## Requisitos

- [x] O organizador sobe fotos pelo painel (várias de uma vez), ordena, liga,
      desliga e apaga.
- [x] A home mostra até 12 fotos ativas, na ordem do organizador, numa faixa
      de 100% da largura: 6×2 no desktop, 2×3 (as 6 primeiras) no celular.
- [x] Foto pode ter um link (o post no Instagram, por exemplo); com link,
      abre em aba nova.
- [x] Sem foto ativa, a seção não aparece.
- [x] Foto de celular tirada em pé aparece em pé.
- [x] As fotos chegam de tamanhos e proporções variadas: a grade mostra cada
      uma preenchendo o quadrado, recortada pelo centro, sem vão entre elas;
      no hover, a foto cresce um pouco dentro do quadrado.
- [x] Nenhum organizador enxerga ou mexe na foto de outro.

## Fora de escopo

- **Puxar do Instagram.** Decisão do dono (2026-09-20). Se voltar, entra como
  uma sincronização que grava nesta mesma tabela.
- **Legenda visível na home.** A legenda existe (vira `alt` e `title`), mas a
  faixa mostra só a foto, como um feed.
- **Lightbox / ver a foto inteira no site.** Foi tentado como troca em fade no
  hover e descartado pelo dono (2026-09-20): `contain` deixava a foto menor
  que o recorte — parecia encolher. A derivada inteira continua sendo gravada
  para um lightbox futuro; clique só vai ao link, se houver.
- **Reordenar arrastando.** A ordem é um número, como no patrocinador.
- **Guardar o original.** Só as duas derivadas ficam no bucket; se um dia for
  preciso outro corte, é subir de novo.

## Design

### Nome

"Fotos", nunca "galeria": `config/galeria.php` e `App\Support\GaleriaDeRealizados`
já existem e são a vitrine de provas realizadas — outra coisa. Tabela `photos`,
model `Photo`, rota `/admin/fotos`, seção `#fotos`, classe `.fotos`.

### Tabela `photos`

A foto é do **organizador**, como equipe e patrocinador: a home é dele, e a
galeria não pertence a uma prova.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | id | |
| `organizer_id` | FK → organizers | `cascadeOnDelete` |
| `caption` | string nullable | legenda curta: vira o `alt` e o `title` |
| `link_url` | string nullable | precisa de esquema (`https://`), como o site do patrocinador |
| `position` | integer, default 0 | menor primeiro; empate: mais nova primeiro |
| `active` | boolean, default `true` | tira do site sem apagar |
| timestamps | | `updated_at` é a versão na URL da imagem (cache do CDN) |

**Sem `has_image`**: linha existe ⇒ imagem existe. O painel cria a linha, grava
a imagem e, se a gravação falhar, apaga a linha na hora. O caminho é derivado de
ids, nunca do nome enviado: `publico/organizadores/{organizer_id}/fotos/{id}.jpg`.

### As imagens (`App\Support\ImagensDaFoto`)

Duas derivadas por foto, JPEG qualidade 82:

| Arquivo | O que é | Tamanho |
|---|---|---|
| `{id}.jpg` | o **quadrado** da grade: recorte central, preenchendo | 700×700 (~350 px na tela; o dobro no arquivo para não borrar em tela retina) |
| `{id}-inteira.jpg` | a **foto inteira**, reservada para um "ver inteira" futuro (a faixa não a usa) | cabe num quadro de 1000 px, sem ampliar |

O painel recebe foto de celular de 3–5 MB e 4000 px, e a home mostra 12 de uma
vez — servir o original seria 40 MB numa página. As fotos chegam de proporções
variadas; o recorte pelo centro é o que deixa a grade regular, e a inteira é o
que deixa ver o que ficou de fora. PNG com transparência ganha fundo branco
(JPEG não tem alfa).

**Orientação.** Foto de celular em pé costuma vir "deitada" nos pixels, com a
tag EXIF `Orientation` dizendo "gire 90°". O navegador obedece; o GD não — e a
derivada apareceria virada. A extensão `exif` entrou no `docker/php/Dockerfile`
e a imagem é girada antes do recorte (tags 3, 6 e 8). Sem a extensão, grava sem
girar; nunca falha por isso.

### Painel (`/admin/fotos`)

`PhotoController` no molde do `SponsorController`: lista, cadastro em lote,
edição, exclusão; busca **já filtrando** pelo organizador e 404 se não achar.

- **Cadastro em lote**: um campo de arquivos múltiplos, até 10 por envio. O PHP
  do container aceita 6 MB por arquivo e 16 MB por envio (`docker/php/local.ini`)
  — ~3–4 fotos de celular por vez, e o formulário diz isso. Cada arquivo vira
  uma linha, com `position` seguindo a maior existente; arquivo que falha ao
  processar não deixa linha e é listado no erro, sem derrubar os outros.
- **Edição**: trocar a imagem (regrava o mesmo caminho e dá `touch` para a
  versão mudar), legenda, link, ordem, ativo.
- **Exclusão**: apaga o arquivo do bucket e a linha — o caminho é derivado do
  id, e uma foto futura com o mesmo id herdaria a imagem órfã.

### A faixa na home (`<x-app.fotos>`)

Fica **fora** do `.container` de propósito: é a única faixa da home a 100% da
largura. Título `GALERIA DE FOTOS` no mesmo estilo das outras seções, e a grade:

| Largura | Colunas | Fotos |
|---|---|---|
| > 1024px | 6 | 12 (2 linhas) |
| 641–1024px | 3 | 12 (4 linhas) |
| ≤ 640px | 2 | 6 (3 linhas; da 7ª em diante escondidas por CSS) |

As fotos ficam **encostadas, sem vão** (`gap: 0`). **Hover:** a foto cresce
1,05× dentro do próprio quadrado (0,4 s; o `overflow: hidden` esconde o que
sobra) e volta ao sair. Foto com `link_url` é `<a target="_blank"
rel="noopener">`; sem link, é só `<figure>`. `Photo::naVitrine($organizerId)` filtra
ativas e ordena; a home pega 12. Sem foto, a seção não existe — mesma regra
dos patrocinadores. O menu do site ganhou o link "Fotos".

## Plano de testes

`tests/Feature/Admin/PhotoCrudTest.php` (`Storage::fake('r2')`, dois
organizadores): envio de 3 arquivos cria 3 linhas com `position` sequencial e
3 arquivos no caminho derivado (nunca no nome enviado); não-imagem e envio vazio
recusados; listagem não mostra foto alheia; `edit`/`update`/`destroy` alheios →
404 e a foto do vizinho intacta; edição de legenda, link, ordem e ativo; link
sem `https://` recusado; troca de imagem regrava o mesmo caminho; exclusão
remove linha e arquivo; deslogado → login; atleta → 403.

`tests/Feature/FotosNoSiteTest.php`: sem foto a seção não aparece; ativa
aparece com a URL da imagem; inativa e de outro organizador não; ordem pela
`position`; 14 ativas → 12 na página; com link vira `<a rel="noopener">`, sem
link não; a seção fica depois de `#eventos` e antes de `#patrocinadores`.

`tests/Unit/ImagensDaFotoTest.php`: 1200×800 e 500×900 viram 700×700 JPEG; a
inteira cabe em 1000 mantendo a proporção e não amplia foto pequena; bytes que
não são imagem lançam exceção com mensagem.

## Critérios de aceite

- O organizador sobe várias fotos de uma vez, ordena, desliga e apaga pelo
  painel; foto em pé aparece em pé.
- A home mostra a faixa a 100% da largura, 6×2 no desktop e 2×3 no celular,
  e a esconde sem fotos.
- Nenhum organizador alcança foto de outro.
- A suíte passa inteira; `CHANGELOG.md` e `docs/backlog.md` atualizados.
