@extends('layouts.auth')

@section('title', 'Alterar senha')

@section('content')

    <div class="modal-overlay">
        <div class="modal text-left">

            <form method="POST" action="{{ route('senha.atualizar') }}" class="form-container">
                @csrf
                @method('PUT')

                <h2>Alterar senha</h2>

                <p class="small text-muted" style="margin-bottom: 8px;">
                    {{ Auth::user()->name }} — {{ Auth::user()->email }}
                </p>

                <input type="password" name="senha_atual" placeholder="Senha atual"
                       required autocomplete="current-password">

                <input type="password" name="password" placeholder="Nova senha"
                       required minlength="6" autocomplete="new-password">

                <input type="password" name="password_confirmation" placeholder="Repita a nova senha"
                       required minlength="6" autocomplete="new-password">

                <button type="submit" class="btn-primary">Salvar nova senha</button>

                <div class="form-links">
                    <a href="{{ route('conta.inscricoes') }}">Minha conta</a>
                    <a href="{{ url('/') }}">Voltar ao site</a>
                </div>
            </form>

        </div>
    </div>

@endsection
