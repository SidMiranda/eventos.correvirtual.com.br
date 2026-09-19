<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O cupom informado não pode ser usado nesta inscrição.
 *
 * A mensagem é a que o atleta vai ler — já em português, já dizendo o motivo
 * (não existe neste evento, venceu, esgotou). Quem captura só precisa
 * mostrá-la.
 */
class CupomRecusado extends RuntimeException
{
}
