<?php

namespace App\Http\Controllers\Conta;

use App\Http\Controllers\Controller;
use App\Support\InscricoesDoAtleta;

/**
 * "Minha conta" › Inscrições. Ver docs/specs/area-do-atleta.md.
 */
class InscricoesDoAtletaController extends Controller
{
    public function index()
    {
        $inscricoes = InscricoesDoAtleta::de(auth()->user(), app('currentOrganizer')->id);

        return view('conta.inscricoes', compact('inscricoes'));
    }
}
