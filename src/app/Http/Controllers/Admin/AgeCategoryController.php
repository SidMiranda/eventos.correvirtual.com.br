<?php

namespace App\Http\Controllers\Admin;

use App\Models\AgeCategory;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Categorias etárias de um evento: desconto por idade.
 *
 * Não é kit nem modalidade — criança e idoso recebem o mesmo kit e pagam
 * menos. A idade vem do cadastro do atleta, pelo critério do evento (ver o
 * formulário do evento). Ver ADR 0007.
 */
class AgeCategoryController extends AdminController
{
    public function index(int $eventoId)
    {
        $event = $this->eventoDoOrganizador($eventoId);
        $categories = $event->ageCategories()->orderBy('min_age')->orderBy('name')->get();

        return view('admin.age_categories.index', compact('event', 'categories'));
    }

    public function create(int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        return view('admin.age_categories.create', compact('event'));
    }

    public function store(Request $request, int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        $event->ageCategories()->create($this->validar($request));

        return redirect()
            ->route('admin.eventos.categorias.index', $event->id)
            ->with('sucesso', 'Categoria criada.');
    }

    public function edit(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $category = $this->categoria($event, $id);

        return view('admin.age_categories.edit', compact('event', 'category'));
    }

    public function update(Request $request, int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $category = $this->categoria($event, $id);

        $category->update($this->validar($request));

        return redirect()
            ->route('admin.eventos.categorias.index', $event->id)
            ->with('sucesso', 'Categoria atualizada.');
    }

    public function destroy(int $eventoId, int $id)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);
        $category = $this->categoria($event, $id);

        if ($category->subscriptions()->exists()) {
            return back()->withErrors([
                'categoria' => 'Esta categoria já foi aplicada em inscrições e por isso não pode ser apagada. Desative-a para deixar de valer.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.eventos.categorias.index', $event->id)
            ->with('sucesso', 'Categoria apagada.');
    }

    private function categoria(Event $event, int $id): AgeCategory
    {
        return $event->ageCategories()->where('id', $id)->firstOrFail();
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'discount_type' => ['required', Rule::in(AgeCategory::TIPOS)],
            'discount_value' => [
                'required', 'numeric', 'min:0.01',
                // Percentual acima de 100 não faz sentido; valor fixo pode ser
                // qualquer coisa — o teto é o preço, na hora da conta.
                $request->input('discount_type') === AgeCategory::TIPO_PERCENTUAL ? 'max:100' : 'max:99999.99',
            ],
            'active' => ['boolean'],
        ], [
            'max_age.gte' => 'A idade máxima não pode ser menor que a mínima.',
            'discount_value.max' => 'Desconto percentual vai até 100%.',
        ]);

        if ($dados['min_age'] === null && $dados['max_age'] === null) {
            // "Qualquer idade" não é categoria etária — é desconto para todo
            // mundo, e para isso existe o cupom.
            return back()->withInput()->withErrors([
                'min_age' => 'Informe a idade mínima, a máxima, ou as duas.',
            ])->throwResponse();
        }

        return $dados + ['active' => $request->boolean('active')];
    }
}
