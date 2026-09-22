<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A busca de municípios que alimenta o campo "Cidade" do cadastro.
 *
 * Pública de propósito: o formulário de cadastro é de quem ainda não tem
 * conta. O que ela expõe é a lista de municípios do IBGE, que é pública —
 * mas o `throttle` da rota está lá para ninguém usar isto como um serviço
 * de consulta às nossas custas.
 *
 * A lista mora no nosso banco (ver `cidades:importar`), não na API do IBGE:
 * uma consulta externa a cada tecla digitada colocaria a inscrição de alguém
 * na dependência de um serviço de fora responder a tempo.
 */
class CityController extends Controller
{
    public function buscar(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        // Abaixo de três letras a busca devolveria meia lista sem ajudar
        // ninguém a escolher. É a regra combinada com o dono.
        if (mb_strlen($termo) < City::MINIMO_DE_LETRAS) {
            return response()->json([]);
        }

        $cidades = City::buscar($termo)
            ->limit(City::LIMITE_DA_BUSCA)
            ->get(['id', 'name', 'state']);

        return response()->json(
            $cidades->map(fn (City $c) => [
                'id' => $c->id,
                'nome' => $c->nomeCompleto(),
            ])
        );
    }
}
