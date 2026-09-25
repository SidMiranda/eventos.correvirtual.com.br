<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O que o atleta pode mudar no próprio cadastro (docs/specs/area-do-atleta.md).
 *
 * A lista é fechada de propósito — data de nascimento, sexo, CPF, CPF do
 * responsável e e-mail ficam travados (decisão do dono, 2026-09-25): a data e
 * o sexo mexem em categoria e preço, o CPF é a identidade na largada, e o
 * e-mail é o login. Mandar esses campos no formulário não muda nada, porque
 * só o que está em dadosDoPerfil() chega ao banco.
 */
class PerfilDoAtletaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // O celular chega mascarado; no banco ele fica só com dígitos, como
        // no cadastro.
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => preg_replace('/\D/', '', (string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:11'],
            'cidade' => ['required', 'string', 'max:120'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'is_pcd' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o seu nome.',
            'phone.required' => 'Informe o seu celular.',
            'phone.min' => 'O celular precisa ter DDD e número.',
            'phone.max' => 'O celular precisa ter DDD e número.',
            'cidade.required' => 'Informe a sua cidade.',
            'city_id.required' => 'Escolha a cidade na lista que aparece enquanto você digita.',
            'city_id.exists' => 'Escolha a cidade na lista que aparece enquanto você digita.',
        ];
    }

    /** Só o que o atleta pode mudar — nada além disto chega ao banco. */
    public function dadosDoPerfil(): array
    {
        return [
            'name' => $this->validated('name'),
            'phone' => $this->validated('phone'),
            'city_id' => (int) $this->validated('city_id'),
            // Checkbox desmarcado não vem no POST, e isso é "não".
            'is_pcd' => $this->boolean('is_pcd'),
        ];
    }
}
