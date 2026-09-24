<?php

namespace App\Http\Controllers\Admin;

use App\Models\EventPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A grade de preços do evento: linhas = modalidade × kit, colunas = lotes.
 *
 * Um formulário só, `precos[modalidade][kit][lote] = valor`. Célula vazia é
 * combinação não vendável naquele lote. Só entram na grade os pares em que o
 * kit está vinculado à modalidade — o vínculo se faz no formulário do kit.
 * Ver ADR 0007.
 */
class EventPriceController extends AdminController
{
    public function edit(int $eventoId)
    {
        $event = $this->eventoDoOrganizador($eventoId);

        $modalities = $event->modalities()->orderBy('distance_km')->orderBy('name')->get();
        $kits = $event->kits()->with('modalities')->orderBy('name')->get();
        $lots = $event->lots()->get();

        // "m-k-l" => "89.90", para a view achar a célula sem consulta.
        $prices = $event->prices()->get()
            ->mapWithKeys(fn (EventPrice $p) => ["{$p->modality_id}-{$p->kit_id}-{$p->lot_id}" => $p->price]);

        return view('admin.prices.edit', compact('event', 'modalities', 'kits', 'lots', 'prices'));
    }

    public function update(Request $request, int $eventoId)
    {
        $event = $this->eventoAbertoDoOrganizador($eventoId);

        $request->validate([
            'precos' => ['nullable', 'array'],
            'precos.*' => ['array'],
            'precos.*.*' => ['array'],
            'precos.*.*.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999.99'],
        ], [
            'precos.*.*.*.min' => 'Preço precisa ser de pelo menos R$ 0,01 — o Pix não aceita cobrança zerada. Deixe vazio para não vender a combinação.',
            'precos.*.*.*.numeric' => 'Preço inválido. Use número, com ponto ou vírgula nos centavos.',
        ]);

        // Só ids DESTE evento contam. Qualquer outro no formulário é
        // adulteração e é ignorado — nunca gravado.
        $modalidades = $event->modalities()->pluck('id')->flip();
        $kits = $event->kits()->with('modalities')->get()->keyBy('id');
        $lotes = $event->lots()->pluck('id')->flip();

        $gravados = 0;
        $apagados = 0;

        DB::transaction(function () use ($request, $event, $modalidades, $kits, $lotes, &$gravados, &$apagados) {
            foreach ((array) $request->input('precos', []) as $modalidadeId => $porKit) {
                if (! isset($modalidades[$modalidadeId])) {
                    continue;
                }

                foreach ((array) $porKit as $kitId => $porLote) {
                    $kit = $kits[$kitId] ?? null;
                    // Kit fora da modalidade não tem célula: vínculo é no kit.
                    if (! $kit || ! $kit->modalities->contains('id', (int) $modalidadeId)) {
                        continue;
                    }

                    foreach ((array) $porLote as $loteId => $valor) {
                        if (! isset($lotes[$loteId])) {
                            continue;
                        }

                        $chave = ['modality_id' => (int) $modalidadeId, 'kit_id' => (int) $kitId, 'lot_id' => (int) $loteId];

                        if ($valor === null || $valor === '') {
                            $apagados += EventPrice::where($chave)->delete();
                            continue;
                        }

                        EventPrice::updateOrCreate($chave, ['event_id' => $event->id, 'price' => (float) $valor]);
                        $gravados++;
                    }
                }
            }
        });

        return redirect()
            ->route('admin.eventos.precos.edit', $event->id)
            ->with('sucesso', "Grade salva: {$gravados} preço(s) gravado(s)" . ($apagados ? ", {$apagados} célula(s) esvaziada(s)." : '.'));
    }
}
