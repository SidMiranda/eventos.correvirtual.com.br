<?php

namespace App\Http\Controllers\Conta;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerfilDoAtletaRequest;

/**
 * "Minha conta" › Perfil. O que pode e o que não pode mudar está em
 * PerfilDoAtletaRequest; ver docs/specs/area-do-atleta.md.
 */
class PerfilDoAtletaController extends Controller
{
    public function edit()
    {
        return view('conta.perfil', ['atleta' => auth()->user()->load('city')]);
    }

    public function update(PerfilDoAtletaRequest $request)
    {
        $request->user()->update($request->dadosDoPerfil());

        return redirect()->route('conta.perfil')->with('success', 'Perfil atualizado.');
    }
}
