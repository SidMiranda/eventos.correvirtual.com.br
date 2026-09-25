<header class="cv-nav" id="cv-nav">
    <div class="cv-nav__utility">
        <div class="cv-nav__utility-inner">
            <span class="cv-nav__utility-tagline">Provas presenciais e virtuais</span>
        </div>
    </div>

    <div class="cv-nav__main">
        <div class="cv-nav__main-inner">
            <a href="/" class="cv-nav__brand">{{ $organizerName }}</a>

            {{-- Atalho para a "Minha conta" ao lado do hambúrguer, só no celular
                 (no desktop o link já está à vista no menu). Sem login, a rota
                 manda para o login e volta para a conta depois. --}}
            <a href="/minha-conta" class="cv-nav__conta" aria-label="Minha conta" title="Minha conta">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </a>

            <button type="button" class="cv-nav__toggle" id="cv-nav-toggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="cv-nav-links">
                <span></span><span></span><span></span>
            </button>

            <nav class="cv-nav__links" id="cv-nav-links">
                <a href="#eventos">Eventos</a>
                <a href="#fotos">Fotos</a>
                <a href="#sobre">Sobre</a>
                <a href="#patrocinadores">Patrocinadores</a>

                <div class="cv-nav__auth">
                    @auth
                        <a href="/minha-conta">Minha conta</a>
                        <span class="cv-nav__auth-user">{{ Auth::user()->name }}</span>
                        <form method="POST" action="/logout" class="cv-nav__logout-form">
                            @csrf
                            <button type="submit">Sair</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="cv-nav__auth-ghost">Entrar</a>
                        <a href="{{ route('register') }}" class="cv-nav__links-cta">Criar conta</a>
                    @endauth
                </div>
            </nav>
        </div>
    </div>
</header>
