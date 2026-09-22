<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmailCode;
use App\Http\Controllers\Controller;

class RegisterController extends Controller
{
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // Remove pontos e traços do CPF antes da validação
        if ($request->has('cpf')) {
            $request->merge([
                'cpf' => preg_replace('/[^0-9]/', '', $request->cpf)
            ]);
        }

        // Remove a formatação do celular (parênteses, espaços e traços)
        if ($request->has('phone')) {
            $request->merge([
                'phone' => preg_replace('/[^0-9]/', '', $request->phone)
            ]);
        }

        // 1. Validação dos dados que vêm do formulário de registro
        $request->validate([
            'name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'sex' => 'required|in:male,female,other',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'cpf' => 'required|string|size:11|unique:users',
            'password' => 'required|string|min:6', // No futuro colocar regras mais fortes
            // A cidade é opcional (decisão do dono em 2026-09-22: campo
            // obrigatório novo no meio do funil custa inscrição). Mas quem
            // digitou alguma coisa e NÃO escolheu da lista não passa: sem o
            // `required_with`, o texto digitado se perderia em silêncio e a
            // pessoa acharia que tinha informado a cidade.
            'cidade' => 'nullable|string|max:120',
            'city_id' => 'nullable|required_with:cidade|integer|exists:cities,id',
        ], [
            'city_id.required_with' => 'Escolha a cidade na lista que aparece enquanto você digita.',
            'city_id.exists' => 'Escolha a cidade na lista que aparece enquanto você digita.',
        ]);

        // 2. Criação do usuário no banco
        $user = User::create([
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'sex' => $request->sex,
            'city_id' => $request->city_id ?: null,
            'phone' => $request->phone,
            'email' => $request->email,
            'cpf' => $request->cpf,
            'password' => Hash::make($request->password),
            'role' => 'athlete', // Já força o papel correto
            'active' => true,
        ]);

        $code = random_int(1000, 9999);

        $user->email_verification_code = $code;
        $user->save();

        Mail::to($user->email)->send(new VerifyEmailCode($code));

        session(['verification_email' => $user->email]);

        return redirect()->route('verify-email.show')
            ->with('success', 'Enviamos um código de verificação para seu email.');
    }
        

}