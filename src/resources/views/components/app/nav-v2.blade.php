<header class="cv-nav" id="cv-nav">
    <div class="cv-nav__utility">
        <div class="cv-nav__utility-inner">
            <span class="cv-nav__utility-tagline">Provas presenciais e virtuais</span>
        </div>
    </div>

    <div class="cv-nav__main">
        <div class="cv-nav__main-inner">
            <a href="/" class="cv-nav__brand">{{ $organizerName }}</a>

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
