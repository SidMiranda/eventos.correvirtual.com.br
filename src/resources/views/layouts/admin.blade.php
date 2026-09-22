<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('titulo', 'Painel') — {{ auth()->user()->organizer->name ?? 'Corre Virtual' }}</title>

    {{-- Cartão de pré-visualização do link. O painel é fechado, então o cartão
         não descreve conteúdo nenhum de propósito: quem receber o endereço vê
         que é uma área restrita, e não um pedaço do site do organizador. --}}
    <x-app.og
        tipo="website"
        :titulo="'Painel do organizador — ' . (auth()->user()->organizer->name ?? 'Corre Virtual')"
        descricao="Área restrita para o organizador cadastrar eventos, modalidades, kits e equipes."
        :imagem="\App\Support\Arquivos::bannerDoOrganizador(auth()->user()->organizer_id)" />

    {{-- Painel não entra em busca: é área logada. --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- SB Admin Pro (TEMPLATES/Painel-Admin/), copiado para public/assets/admin/.
         Só o painel usa isso — o site público continua sem Bootstrap/jQuery.

         O caminho é assets/admin/ e NÃO admin/ de propósito: uma pasta física
         public/admin/ faz o nginx (try_files $uri $uri/) achar o diretório antes
         de chegar no Laravel e devolver 403 na rota /admin do painel. --}}
    <link href="{{ asset('assets/admin/css/styles.css') }}?v={{ filemtime(public_path('assets/admin/css/styles.css')) }}" rel="stylesheet">
    <script data-search-pseudo-elements defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/js/all.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.24.1/feather.min.js" crossorigin="anonymous"></script>

    <style>
        /* Recoloração para a paleta do projeto (mesma da Home v2): o template vem
           num azul/roxo próprio que não conversa com o site público. */
        :root {
            --cv-navy: #0d1b2a;
            --cv-blue: #1a71b2;
            --cv-blue-pale: #eaf4fb;
        }
        /* Cabeçalho com a ponte de Mogi Guaçu, a mesma imagem do banner do site
           público — o painel deixa de ser um degradê genérico e passa a ter a
           cara do organizador. O degradê escuro por cima é o que mantém o texto
           branco legível sobre qualquer parte da foto. */
        .bg-gradient-primary-to-secondary {
            background:
                linear-gradient(90deg, rgba(13,27,42,.94) 0%, rgba(13,27,42,.72) 45%, rgba(26,113,178,.62) 100%),
                url('{{ \App\Support\Arquivos::imagemDaHome('banner-1-organizer-cropped.jpg') }}') center 38% / cover no-repeat,
                var(--cv-navy) !important;
        }
        .btn-primary { background-color: var(--cv-blue); border-color: var(--cv-blue); }
        .btn-primary:hover, .btn-primary:focus { background-color: var(--cv-navy); border-color: var(--cv-navy); }
        .navbar-brand-cv { color: #fff; font-weight: 600; letter-spacing: .01em; font-size: 1.05rem; }
        .topnav.navbar-cv { background: var(--cv-navy) !important; }
        .topnav.navbar-cv .nav-link, .topnav.navbar-cv .navbar-brand-cv { color: #fff !important; }

        /* Botões da barra escura: brancos. O template usa btn-transparent-dark,
           que pinta o ícone da própria cor do fundo — invisível. */
        .botao-menu, .botao-topo {
            color: #fff !important; background: transparent; border: 0; padding: .35rem .5rem;
        }
        .botao-menu:hover, .botao-menu:focus,
        .botao-topo:hover, .botao-topo:focus {
            color: #fff !important; background: rgba(255,255,255,.14);
        }
        .botao-menu svg, .botao-topo svg { width: 1.35rem; height: 1.35rem; stroke-width: 2.25; }
        .topnav .btn-icon { color: #fff; }

        /* Brasão da equipe: sempre redondo e do mesmo tamanho, com ou sem
           imagem — assim a coluna de nomes fica alinhada na listagem. */
        .brasao-equipe {
            width: 38px; height: 38px; flex: 0 0 38px;
            border-radius: 50%;
            object-fit: cover;
            background: #fff;
            border: 1px solid #dbe3ec;
        }
        .brasao-equipe--vazio {
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--cv-blue-pale);
            color: var(--cv-navy);
            font-size: 12px; font-weight: 700; letter-spacing: .02em;
        }
        .brasao-equipe--grande { width: 72px; height: 72px; flex-basis: 72px; }

        /* Logo de patrocinador: retangular e não redondo como o brasão — logo
           de marca é deitada, e recortar em círculo comeria o nome. Fundo
           branco porque a maioria é feita para fundo claro; `contain` porque
           cada uma vem numa proporção. */
        .logo-patrocinador {
            width: 76px; height: 38px; flex: 0 0 76px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #dbe3ec;
            overflow: hidden;
        }
        .logo-patrocinador img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-patrocinador--vazio { background: var(--cv-blue-pale); color: #8fa5ba; }
        .logo-patrocinador--vazio .feather { width: 16px; height: 16px; }
        .logo-patrocinador--grande { width: 148px; height: 74px; flex-basis: 148px; }

        /* Selo de autoria: presente, nunca protagonista. */
        .selo-mobspot a { color: inherit; text-decoration: none; opacity: .7; transition: opacity .15s ease; }
        .selo-mobspot a:hover, .selo-mobspot a:focus-visible { opacity: 1; text-decoration: underline; }

        /* Logo do organizador na barra: altura casada com a linha do texto,
           fundo branco porque a maioria das logos é feita para fundo claro. */
        .logo-topo {
            height: 26px; width: auto; max-width: 96px;
            border-radius: 4px; background: #fff; padding: 2px;
        }
        .sidenav-menu .nav-link.active { color: var(--cv-navy); font-weight: 600; background: var(--cv-blue-pale); }
        .text-cv-blue { color: var(--cv-blue) !important; }

        /*
        | Cor nos ícones
        |---------------------------------------------------------------------
        | O template entrega tudo em cinza. Num painel de poucas áreas, dar uma
        | cor a cada uma vira atalho de leitura: a pessoa aprende "kit é laranja"
        | e para de ler o texto do menu.
        |
        | São tons de uma mesma família (azul do projeto, verde, laranja, roxo),
        | todos com contraste suficiente sobre o fundo claro do menu — não é
        | arco-íris, é código de cor.
        */
        :root {
            --icone-painel:      #1a71b2;  /* azul do projeto */
            --icone-eventos:     #1c7a4f;  /* verde: o que está por vir */
            --icone-modalidades: #b45309;  /* âmbar: as distâncias */
            --icone-kits:        #7c3aed;  /* roxo: o que o atleta recebe */
            --icone-cupons:      #4338ca;  /* índigo: o que barateia o kit */
            --icone-equipes:     #be185d;  /* magenta: gente */
            --icone-patrocinadores: #0f766e;  /* teal: dinheiro que entra */
            --icone-fotos:       #475569;  /* grafite: o único neutro, como uma foto */
            --icone-inscricoes:  #0369a1;  /* azul profundo: o movimento do evento */
            --icone-atletas:     #9a3412;  /* terracota: gente que corre */
            --icone-sobre:       #0d9488;  /* verde-água: a apresentação da casa */
        }

        /* Miniatura da galeria no painel: sempre quadrada, como na home. */
        .foto-painel {
            aspect-ratio: 1 / 1;
            width: 100%;
            object-fit: cover;
            background: var(--cv-blue-pale);
        }
        .foto-painel--grande { width: 148px; height: 148px; flex: 0 0 148px; border-radius: 6px; }
        .foto-painel--inativa { opacity: .55; }

        /* Os seletores abaixo precisam ser tão específicos quanto os do template
           (`.sidenav .sidenav-menu .nav .nav-link .nav-link-icon .feather`),
           senão o cinza dele vence e nada muda. */
        .sidenav .sidenav-menu .nav .nav-link .nav-link-icon .feather { stroke-width: 2; }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-painel .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-painel .nav-link-icon .feather { color: var(--icone-painel); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-eventos .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-eventos .nav-link-icon .feather { color: var(--icone-eventos); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-modalidades .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-modalidades .nav-link-icon .feather { color: var(--icone-modalidades); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-kits .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-kits .nav-link-icon .feather { color: var(--icone-kits); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-cupons .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-cupons .nav-link-icon .feather { color: var(--icone-cupons); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-equipes .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-equipes .nav-link-icon .feather { color: var(--icone-equipes); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-patrocinadores .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-patrocinadores .nav-link-icon .feather { color: var(--icone-patrocinadores); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-fotos .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-fotos .nav-link-icon .feather { color: var(--icone-fotos); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-sobre .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-sobre .nav-link-icon .feather { color: var(--icone-sobre); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-inscricoes .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-inscricoes .nav-link-icon .feather { color: var(--icone-inscricoes); }

        .sidenav .sidenav-menu .nav .nav-link.nav-icone-atletas .nav-link-icon,
        .sidenav .sidenav-menu .nav .nav-link.nav-icone-atletas .nav-link-icon .feather { color: var(--icone-atletas); }

        /* No item ativo o ícone não muda de cor — é a faixa clara atrás que
           marca onde você está, então a cor continua servindo de referência. */

        /* Ícone do cabeçalho de cada página, sobre o banner escuro. */
        .page-header-icon svg { color: #fff; opacity: .9; }

        /* Ações das tabelas: cinza em repouso, cor na intenção. Colorir todos
           deixaria a lista poluída — a cor aqui serve para dizer "isto apaga". */
        .btn-datatable[title="Editar"]:hover  { color: var(--icone-painel) !important; }
        .btn-datatable[title="Kits"]:hover    { color: var(--icone-kits) !important; }
        .btn-datatable[title="Modalidades"]:hover { color: var(--icone-modalidades) !important; }
        .btn-datatable[title="Apagar"]:hover  { color: #b3261e !important; }
    </style>
</head>
<body class="nav-fixed">

    <nav class="topnav navbar navbar-expand shadow navbar-dark navbar-cv" id="sidenavAccordion">
        {{-- Logo + nome + botão do menu, nesta ordem e colados, no celular e no
             desktop. Antes o nome sumia abaixo de 576px (d-none d-sm-block) e o
             botão ia parar no canto oposto da barra (order-1), separado da
             marca. --}}
        <a class="navbar-brand-cv ml-3 mr-1 d-flex align-items-center" href="{{ route('admin.dashboard') }}">
            @if (auth()->user()->organizer_id)
                <img src="{{ \App\Support\Arquivos::logoDoOrganizador(auth()->user()->organizer_id) }}"
                     alt="" class="logo-topo mr-2" onerror="this.remove();">
            @endif
            Corre Virtual
        </a>

        {{-- O botão vinha do template com a cor do próprio fundo escuro
             (btn-transparent-dark) e num tamanho menor que o texto ao lado —
             praticamente invisível no celular, que é justo onde ele é a única
             forma de abrir o menu. Agora é branco e do corpo do nome. --}}
        <button class="btn btn-icon botao-menu" id="sidebarToggle" aria-label="Abrir menu">
            <i data-feather="menu"></i>
        </button>

        <ul class="navbar-nav align-items-center ml-auto">
            {{-- Atalho para o site público, à esquerda do perfil. Saiu do rodapé
                 (onde ninguém rolava até) e virou ícone na barra.

                 O endereço vem do domínio do organizador, não de url('/'): no
                 domínio do painel, url('/') aponta para a raiz dele mesmo, que
                 o nginx manda de volta para /admin. --}}
            <li class="nav-item mr-1">
                <a class="btn btn-icon botao-topo" href="{{ auth()->user()->organizer->siteUrl() }}"
                   target="_blank" rel="noopener"
                   title="Ver o site público" aria-label="Ver o site público">
                    <i data-feather="home"></i>
                </a>
            </li>

            <li class="nav-item dropdown no-caret mr-3 dropdown-user">
                {{-- botao-topo no lugar de btn-transparent-dark: a classe do
                     template pinta o ícone da própria cor do fundo escuro da
                     barra, deixando-o praticamente invisível. --}}
                <a class="btn btn-icon botao-topo dropdown-toggle" id="navbarDropdownUserImage"
                   href="javascript:void(0);" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i data-feather="user"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownUserImage">
                    <h6 class="dropdown-header d-flex align-items-center">
                        <div>
                            <div class="dropdown-user-details-name">{{ auth()->user()->name }}</div>
                            <div class="dropdown-user-details-email">{{ auth()->user()->email }}</div>
                        </div>
                    </h6>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            <div class="dropdown-item-icon"><i data-feather="log-out"></i></div>
                            Sair
                        </button>
                    </form>
                </div>
            </li>
        </ul>
    </nav>

    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <nav class="sidenav shadow-right sidenav-light">
                <div class="sidenav-menu">
                    <div class="nav accordion" id="accordionSidenav">

                        <div class="sidenav-menu-heading">Gestão</div>

                        <a class="nav-link nav-icone-painel {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                           href="{{ route('admin.dashboard') }}">
                            <div class="nav-link-icon"><i data-feather="activity"></i></div>
                            Painel
                        </a>

                        <a class="nav-link nav-icone-inscricoes {{ request()->routeIs('admin.inscricoes.*') ? 'active' : '' }}"
                           href="{{ route('admin.inscricoes.index') }}">
                            <div class="nav-link-icon"><i data-feather="clipboard"></i></div>
                            Inscrições
                        </a>

                        <a class="nav-link nav-icone-atletas {{ request()->routeIs('admin.atletas.*') ? 'active' : '' }}"
                           href="{{ route('admin.atletas.index') }}">
                            <div class="nav-link-icon"><i data-feather="user"></i></div>
                            Atletas
                        </a>

                        <a class="nav-link nav-icone-eventos {{ request()->routeIs('admin.eventos.*') ? 'active' : '' }}"
                           href="{{ route('admin.eventos.index') }}">
                            <div class="nav-link-icon"><i data-feather="calendar"></i></div>
                            Eventos
                        </a>

                        <a class="nav-link nav-icone-modalidades {{ request()->routeIs('admin.modalidades.geral') ? 'active' : '' }}"
                           href="{{ route('admin.modalidades.geral') }}">
                            <div class="nav-link-icon"><i data-feather="flag"></i></div>
                            Modalidades
                        </a>

                        <a class="nav-link nav-icone-kits {{ request()->routeIs('admin.kits.geral') ? 'active' : '' }}"
                           href="{{ route('admin.kits.geral') }}">
                            <div class="nav-link-icon"><i data-feather="package"></i></div>
                            Kits
                        </a>

                        <a class="nav-link nav-icone-cupons {{ request()->routeIs('admin.cupons.*') ? 'active' : '' }}"
                           href="{{ route('admin.cupons.index') }}">
                            <div class="nav-link-icon"><i data-feather="tag"></i></div>
                            Cupons
                        </a>

                        <a class="nav-link nav-icone-equipes {{ request()->routeIs('admin.equipes.*') ? 'active' : '' }}"
                           href="{{ route('admin.equipes.index') }}">
                            <div class="nav-link-icon"><i data-feather="users"></i></div>
                            Equipes
                        </a>

                        <a class="nav-link nav-icone-patrocinadores {{ request()->routeIs('admin.patrocinadores.*') ? 'active' : '' }}"
                           href="{{ route('admin.patrocinadores.index') }}">
                            <div class="nav-link-icon"><i data-feather="award"></i></div>
                            Patrocinadores
                        </a>

                        <a class="nav-link nav-icone-fotos {{ request()->routeIs('admin.fotos.*') ? 'active' : '' }}"
                           href="{{ route('admin.fotos.index') }}">
                            <div class="nav-link-icon"><i data-feather="image"></i></div>
                            Fotos
                        </a>

                        <a class="nav-link nav-icone-sobre {{ request()->routeIs('admin.sobre.*') ? 'active' : '' }}"
                           href="{{ route('admin.sobre.edit') }}">
                            <div class="nav-link-icon"><i data-feather="info"></i></div>
                            Sobre nós
                        </a>

                    </div>
                </div>
                {{-- Quem está logado precisa saber de qual organizador é o que
                     está vendo — o painel é multi-organizador, e essa é a única
                     marca fixa disso na tela. O selo de autoria fica só no
                     rodapé da direita. --}}
                <div class="sidenav-footer">
                    <div class="sidenav-footer-content">
                        <div class="sidenav-footer-subtitle">Organizador</div>
                        <div class="sidenav-footer-title">{{ auth()->user()->organizer->name }}</div>
                    </div>
                </div>
            </nav>
        </div>

        <div id="layoutSidenav_content">
            <main>
                {{-- pb-4 e não o pb-10 do template: o SB Admin Pro combina um
                     padding grande aqui com uma margem negativa no conteúdo
                     (mt-n10) para o primeiro bloco invadir o banner de
                     propósito. Fica estranho com os cards do painel, então a
                     sobreposição foi desfeita: banner mais baixo, conteúdo
                     começando logo abaixo dele. --}}
                <header class="page-header page-header-dark bg-gradient-primary-to-secondary pb-4">
                    <div class="container-fluid">
                        <div class="page-header-content pt-4">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-auto mt-4">
                                    <h1 class="page-header-title">
                                        <div class="page-header-icon"><i data-feather="@yield('icone', 'file')"></i></div>
                                        @yield('titulo', 'Painel')
                                    </h1>
                                    @hasSection('subtitulo')
                                        <div class="page-header-subtitle">@yield('subtitulo')</div>
                                    @endif
                                </div>
                                @hasSection('acoes')
                                    <div class="col-12 col-xl-auto mt-4">@yield('acoes')</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </header>

                <div class="container-fluid mt-4">

                    @if (session('sucesso'))
                        <div class="alert alert-success alert-icon" role="alert">
                            <div class="alert-icon-aside"><i class="far fa-check-circle"></i></div>
                            <div class="alert-icon-content">{{ session('sucesso') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger alert-icon" role="alert">
                            <div class="alert-icon-aside"><i class="fas fa-exclamation-triangle"></i></div>
                            <div class="alert-icon-content">
                                @foreach ($errors->all() as $erro)
                                    <div>{{ $erro }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @yield('conteudo')
                </div>
            </main>

            <footer class="footer mt-auto footer-light">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-6 small">Corre Virtual — painel do organizador</div>
                        <div class="col-md-6 text-md-right small selo-mobspot">
                            <a href="https://mobspot.com.br/?utm_source=corre-virtual-admin&utm_medium=selo-rodape&utm_campaign=rede-clientes"
                               target="_blank" rel="noopener">Desenvolvido por Mobspot<span>.</span></a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('assets/admin/js/scripts.js') }}"></script>
    <script>if (window.feather) { feather.replace(); }</script>
    @stack('scripts')
</body>
</html>
