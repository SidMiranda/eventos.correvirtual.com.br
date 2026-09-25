{{-- As abas da "Minha conta". Uma aba nova é uma linha aqui. --}}
<nav class="conta__abas" aria-label="Minha conta">
    <a href="{{ route('conta.inscricoes') }}"
       class="{{ request()->routeIs('conta.inscricoes', 'subscriptions.my') ? 'ativa' : '' }}"
       @if (request()->routeIs('conta.inscricoes', 'subscriptions.my')) aria-current="page" @endif>Inscrições</a>
    <a href="{{ route('conta.perfil') }}"
       class="{{ request()->routeIs('conta.perfil') ? 'ativa' : '' }}"
       @if (request()->routeIs('conta.perfil')) aria-current="page" @endif>Perfil</a>
</nav>
