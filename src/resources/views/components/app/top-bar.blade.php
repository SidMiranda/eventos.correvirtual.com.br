<nav class="topnav navbar navbar-expand shadow navbar-light bg-white" id="sidenavAccordion">
    <a class="navbar-brand" href="/">{{ $organizerName }}</a>

    <ul class="navbar-nav align-items-center ml-auto">
        <li class="nav-item dropdown no-caret mr-3">
            <a class="nav-link dropdown-toggle"
                id="navbarDropdownDocs"
                href="javascript:void(0);"
                role="button"
                data-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">
                <div class="d-inline font-weight-500">
                    @auth
                        <span>
                            <a href="/my-subscriptions">

                                <span class="d-none d-md-inline">Minhas inscrições</span>
                            </a>
                        </span>
                    @else
                        <a href="{{ route('login') }}" class="d-none d-md-inline">
                            Login
                        </a>
                    @endauth
                </div>
            </a>

        </li>
        @auth
            <li class="nav-item mr-3 d-md-none">
                <a class="btn btn-icon btn-transparent-dark" href="/my-subscriptions" title="Minhas Inscrições">
                    <i data-feather="check-square"></i>
                </a>
            </li>
            <li class="nav-item dropdown no-caret mr-3 dropdown-notifications">
                <a class="btn btn-icon btn-transparent-dark dropdown-toggle"
                    id="navbarDropdownAlerts"
                    href="javascript:void(0);"
                    role="button"
                    data-toggle="dropdown"
                    aria-haspopup="true"
                    aria-expanded="false">
                    <i data-feather="bell"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right border-0 shadow animated--fade-in-up"
                    aria-labelledby="navbarDropdownAlerts">
                    <h6 class="dropdown-header dropdown-notifications-header">
                        <i class="mr-2" data-feather="bell"></i>
                        Notificações
                    </h6>
                    {{-- <a class="dropdown-item dropdown-notifications-item" href="#!"
                        ><div class="dropdown-notifications-item-icon bg-warning"><i data-feather="activity"></i></div>
                        <div class="dropdown-notifications-item-content">
                            <div class="dropdown-notifications-item-content-details">December 29, 2019</div>
                            <div class="dropdown-notifications-item-content-text">This is an alert message. It's nothing serious, but it requires your attention.</div>
                        </div></a>
                     <a class="dropdown-item dropdown-notifications-item" href="#!"
                        ><div class="dropdown-notifications-item-icon bg-info"><i data-feather="bar-chart"></i></div>
                        <div class="dropdown-notifications-item-content">
                            <div class="dropdown-notifications-item-content-details">December 22, 2019</div>
                            <div class="dropdown-notifications-item-content-text">A new monthly report is ready. Click here to view!</div>
                        </div></a
                    ><a class="dropdown-item dropdown-notifications-item" href="#!"
                        ><div class="dropdown-notifications-item-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="dropdown-notifications-item-content">
                            <div class="dropdown-notifications-item-content-details">December 8, 2019</div>
                            <div class="dropdown-notifications-item-content-text">Critical system failure, systems shutting down.</div>
                        </div></a
                    ><a class="dropdown-item dropdown-notifications-item" href="#!"
                        ><div class="dropdown-notifications-item-icon bg-success"><i data-feather="user-plus"></i></div>
                        <div class="dropdown-notifications-item-content">
                            <div class="dropdown-notifications-item-content-details">December 2, 2019</div>
                            <div class="dropdown-notifications-item-content-text">New user request. Woody has requested access to the organization.</div>
                        </div></a
                    >--}}
                    <a class="dropdown-item dropdown-notifications-footer" href="#!">Nenhuma notificação</a>
                </div>
            </li>
        @endauth
        <li class="nav-item dropdown no-caret mr-3 dropdown-user">
            <a class="btn btn-icon btn-transparent-dark dropdown-toggle"
                id="navbarDropdownUserImage"
                href="javascript:void(0);"
                role="button"
                data-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">
                <img class="img-fluid" src="{{ \App\Support\Arquivos::usuarioPadrao() }}" />
            </a>
            <div class="dropdown-menu dropdown-menu-right border-0 shadow animated--fade-in-up" aria-labelledby="navbarDropdownUserImage">
                <h6 class="dropdown-header d-flex align-items-center">
                    <img class="dropdown-user-img" src="{{ \App\Support\Arquivos::usuarioPadrao() }}" />
                    <div class="dropdown-user-details">
                        <div class="dropdown-user-details-name">
                            @auth
                                <span>
                                    👤 {{ Auth::user()->name }}
                                </span>
                            @else
                                <a href="{{ route('login') }}">
                                    Login
                                </a>
                            @endauth
                        </div>
                        <div class="dropdown-user-details-email">
                            @auth
                                <span>
                                    {{ Auth::user()->email }}
                                </span>
                            @endauth
                        </div>
                    </div>
                </h6>
                @auth
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/my-subscriptions">
                        <div class="dropdown-item-icon">
                            <i data-feather="check-square"></i>
                        </div>
                        Minhas inscrições
                    </a>
                    <a class="dropdown-item" href="{{ route('senha.editar') }}">
                        <div class="dropdown-item-icon">
                            <i data-feather="settings"></i>
                        </div>
                        Alterar senha
                    </a>
                    <a class="dropdown-item" href="/logout">
                         <div class="dropdown-item-icon"><i data-feather="log-out"></i></div>
                        Sair
                    </a>
                @endauth
            </div>
        </li>
    </ul>
</nav>
