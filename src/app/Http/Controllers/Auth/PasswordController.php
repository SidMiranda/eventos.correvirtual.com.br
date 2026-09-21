<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Troca de senha de quem já está logado.
 *
 * O item "Alterar senha" existia no menu desde sempre apontando para `#!` —
 * um link que não levava a lugar nenhum. Esta é a tela que faltava.
 *
 * Não é o "esqueci minha senha" (recuperação por e-mail), que continua não
 * existindo — ver docs/backlog.md.
 */
class PasswordController extends Controller
{
    public function edit()
    {
        return view('auth.alterar-senha');
    }

    public function update(Request $request)
    {
        $request->validate([
            // `current_password` confere contra a senha do usuário logado. Sem
            // isso, quem senta numa sessão aberta troca a senha e toma a conta.
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:senha_atual', Password::min(6)],
        ], [
            'senha_atual.required' => 'Informe a sua senha atual.',
            'senha_atual.current_password' => 'A senha atual não confere.',
            'password.required' => 'Informe a nova senha.',
            'password.confirmed' => 'A confirmação não é igual à nova senha.',
            'password.different' => 'A nova senha precisa ser diferente da atual.',
            'password.min' => 'A nova senha precisa ter pelo menos 6 caracteres.',
        ]);

        $usuario = $request->user();
        $usuario->password = Hash::make($request->input('password'));
        $usuario->save();

        // A sessão atual continua valendo (quem trocou não é deslogado), mas
        // ganha um id novo: se alguém tinha o id antigo, ele deixa de servir.
        $request->session()->regenerate();

        return redirect()
            ->route('senha.editar')
            ->with('success', 'Senha alterada. Use a nova na próxima vez que entrar.');
    }
}
