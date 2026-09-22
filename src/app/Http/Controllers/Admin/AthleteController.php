<?php

namespace App\Http\Controllers\Admin;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Os atletas inscritos nos eventos do organizador.
 *
 * "Atleta do organizador" não é uma relação que exista no banco: a conta é da
 * plataforma, e `users.organizer_id` só é usado para administradores (e nem
 * chega a ser preenchido no cadastro — BUG-006). O que liga um atleta a este
 * painel é ter ao menos uma inscrição num evento dele. É esse o filtro de tudo
 * aqui, e é por isso que atleta sem inscrição em evento meu dá 404.
 *
 * Só consulta: o organizador não edita cadastro alheio. Ver
 * docs/specs/gestao-de-inscricoes.md.
 */
class AthleteController extends AdminController
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('busca'));

        $atletas = $this->osQueSeInscreveram()
            ->when($busca !== '', function ($q) use ($busca) {
                $termo = '%' . $busca . '%';
                $digitos = preg_replace('/\D/', '', $busca);

                $q->where(function ($u) use ($termo, $digitos) {
                    $u->where('name', 'like', $termo)
                        ->orWhere('email', 'like', $termo)
                        ->when($digitos !== '', fn ($c) => $c->orWhere('cpf', 'like', "%{$digitos}%"));
                });
            })
            ->with('city')
            ->withCount(['subscriptions as inscricoes_count' => fn ($q) => $this->apenasNosMeusEventos($q)])
            ->withMax(['subscriptions as ultima_inscricao' => fn ($q) => $this->apenasNosMeusEventos($q)], 'created_at')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.athletes.index', compact('atletas', 'busca'));
    }

    public function show(int $id)
    {
        $atleta = $this->osQueSeInscreveram()->with('city')->where('users.id', $id)->firstOrFail();

        // As inscrições dele NOS MEUS eventos. As de outro organizador não
        // aparecem: não são da minha conta, no sentido literal.
        $inscricoes = Subscription::where('user_id', $atleta->id)
            ->whereHas('event', fn ($q) => $q->where('organizer_id', $this->organizerId()))
            ->with(['event', 'modality', 'kit', 'coupon'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.athletes.show', compact('atleta', 'inscricoes'));
    }

    /*
    |--------------------------------------------------------------------------
    | Apoio
    |--------------------------------------------------------------------------
    */

    private function osQueSeInscreveram()
    {
        return User::whereHas('subscriptions', fn ($q) => $this->apenasNosMeusEventos($q));
    }

    private function apenasNosMeusEventos($query)
    {
        $organizerId = $this->organizerId();

        return $query->whereHas('event', fn ($e) => $e->where('organizer_id', $organizerId));
    }
}
