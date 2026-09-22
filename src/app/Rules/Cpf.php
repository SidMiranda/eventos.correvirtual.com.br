<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF com os dígitos verificadores conferidos.
 *
 * Até 2026-09-22 o cadastro só exigia `size:11` — "11111111111" entrava sem
 * reclamação. O CPF é o que identifica o atleta na largada e no comprovante
 * de pagamento; número inventado só aparece como problema no dia da prova,
 * quando não dá mais para corrigir.
 *
 * A conta é a oficial da Receita: cada dígito verificador é o resto da soma
 * ponderada dos anteriores por 11.
 */
class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('O :attribute informado não é válido.');
        }
    }

    public static function valido(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // 00000000000, 11111111111... passam na conta dos dígitos, mas não
        // são CPF de ninguém — é o que alguém digita para escapar do campo.
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        foreach ([9, 10] as $posicao) {
            $soma = 0;

            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }

            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
