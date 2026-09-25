<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Validation\Rule;
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

        // O CPF do responsável chega mascarado pela mesma razão do outro.
        if ($request->has('guardian_cpf')) {
            $request->merge([
                'guardian_cpf' => preg_replace('/[^0-9]/', '', $request->guardian_cpf)
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
            // `size:11` sozinho aceitava "11111111111". O CPF identifica o
            // atleta na largada e no comprovante de pagamento: número
            // inventado só aparece como problema no dia da prova.
            'cpf' => ['required', 'string', 'size:11', new Cpf, 'unique:users'],
            // Menor de idade não responde por si num contrato — e a inscrição
            // é um: tem pagamento, termo e risco físico. Exigido só de quem
            // informa nascimento de menos de 18 anos, conferido no servidor
            // porque o campo some da tela por JavaScript.
            'guardian_cpf' => [
                Rule::requiredIf(fn () => User::ehMenorDeIdade($request->input('birth_date'))),
                'nullable',
                'string',
                'size:11',
                new Cpf,
                'different:cpf',
            ],
            'password' => 'required|string|min:6', // No futuro colocar regras mais fortes
            // Obrigatória, e obrigatoriamente escolhida da lista (decisão do
            // dono em 2026-09-22). O que vale é o `city_id`: digitar o nome e
            // não clicar na sugestão não conta, senão o dado chegaria como
            // texto solto e "Mogi Guaçu" e "mogi guacu" virariam duas cidades.
            'cidade' => 'required|string|max:120',
            'city_id' => 'required|integer|exists:cities,id',
        ], [
            'cidade.required' => 'Informe a sua cidade.',
            'city_id.required' => 'Escolha a cidade na lista que aparece enquanto você digita.',
            'city_id.exists' => 'Escolha a cidade na lista que aparece enquanto você digita.',
            'cpf.size' => 'O CPF precisa ter 11 dígitos.',
            'guardian_cpf.required' => 'Quem tem menos de 18 anos precisa informar o CPF do responsável.',
            'guardian_cpf.size' => 'O CPF do responsável precisa ter 11 dígitos.',
            'guardian_cpf.different' => 'O CPF do responsável não pode ser o mesmo do atleta.',
        ], [
            'cpf' => 'CPF',
            'guardian_cpf' => 'CPF do responsável',
        ]);

        // 2. Criação do usuário no banco
        $user = User::create([
            'name' => $request->name,
            'birth_date' => $request->birth_date,
            'sex' => $request->sex,
            'city_id' => $request->city_id,
            'phone' => $request->phone,
            'email' => $request->email,
            'cpf' => $request->cpf,
            'guardian_cpf' => $request->guardian_cpf ?: null,
            // Só informação para o organizador; checkbox desmarcado não vem
            // no POST, e isso é "não".
            'is_pcd' => $request->boolean('is_pcd'),
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