<?php

namespace App\Http\Controllers\Admin;

use App\Models\Photo;
use App\Support\ImagemPublica;
use App\Support\ImagensDaFoto;
use Illuminate\Http\Request;

/**
 * A galeria de fotos da home.
 *
 * Como a equipe e o patrocinador, a foto pertence ao organizador — a home é
 * dele. O que é diferente aqui: o cadastro é em LOTE (um envio, várias fotos),
 * porque subir doze fotos uma a uma é castigo, e a linha só existe com a
 * imagem gravada — falhou a imagem, a linha vai embora na hora.
 *
 * Ver docs/specs/galeria-de-fotos.md.
 */
class PhotoController extends AdminController
{
    /** Por envio. O PHP aceita 16 MB no total (docker/php/local.ini). */
    public const MAXIMO_POR_ENVIO = 10;

    public function index()
    {
        $photos = Photo::where('organizer_id', $this->organizerId())
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.photos.index', compact('photos'));
    }

    public function create()
    {
        return view('admin.photos.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'fotos' => ['required', 'array', 'min:1', 'max:' . self::MAXIMO_POR_ENVIO],
            'fotos.*' => [
                'required',
                'image',
                'mimes:' . implode(',', ImagemPublica::EXTENSOES),
                'max:' . ImagemPublica::TAMANHO_MAXIMO_KB,
            ],
        ], [
            'fotos.required' => 'Escolha pelo menos uma foto.',
            'fotos.max' => 'Envie no máximo ' . self::MAXIMO_POR_ENVIO . ' fotos por vez.',
            'fotos.*.image' => 'Um dos arquivos não é imagem (JPG, PNG ou WEBP).',
            'fotos.*.mimes' => 'Um dos arquivos não é JPG, PNG nem WEBP.',
            'fotos.*.max' => 'Uma das fotos passou de 5 MB.',
        ]);

        // Entram no fim da fila: quem já ordenou a galeria não vê tudo mudar
        // de lugar porque subiu mais três.
        $posicao = (int) Photo::where('organizer_id', $this->organizerId())->max('position');
        $criadas = 0;
        $erros = [];

        foreach ($request->file('fotos') as $arquivo) {
            $photo = Photo::create([
                'organizer_id' => $this->organizerId(),
                'position' => ++$posicao,
                'active' => true,
            ]);

            try {
                ImagensDaFoto::salvar($photo, $arquivo);
                $criadas++;
            } catch (\Throwable $e) {
                // Linha sem imagem seria um buraco na faixa da home.
                $photo->delete();
                $erros[] = $arquivo->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        $resposta = redirect()->route('admin.fotos.index');

        if ($criadas > 0) {
            $resposta->with('sucesso', $criadas === 1 ? '1 foto adicionada.' : "{$criadas} fotos adicionadas.");
        }

        return $erros ? $resposta->withErrors(['fotos' => $erros]) : $resposta;
    }

    public function edit(int $id)
    {
        $photo = $this->buscarDoOrganizador($id);

        return view('admin.photos.edit', compact('photo'));
    }

    public function update(Request $request, int $id)
    {
        $photo = $this->buscarDoOrganizador($id);

        $dados = $request->validate([
            'caption' => ['nullable', 'string', 'max:160'],
            // `url` exige esquema: sem isso, "instagram.com/p/…" digitado sem o
            // https viraria link relativo e levaria para dentro do site.
            'link_url' => ['nullable', 'url', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'foto' => ImagemPublica::regraDeValidacao(),
            'active' => ['boolean'],
        ], [
            'link_url.url' => 'O link precisa começar com https:// (ex.: https://www.instagram.com/p/…).',
            'foto.image' => 'O arquivo precisa ser uma imagem (JPG, PNG ou WEBP).',
            'foto.max' => 'A foto passou de 5 MB.',
        ]);

        unset($dados['foto']);

        $photo->fill($dados + [
            'position' => (int) $request->input('position', 0),
            'active' => $request->boolean('active'),
        ]);

        if ($request->hasFile('foto')) {
            try {
                ImagensDaFoto::salvar($photo, $request->file('foto'));
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors(['foto' => $e->getMessage()]);
            }

            // O caminho não muda ao trocar a imagem, e o CDN guarda a versão
            // antiga; o que muda a URL é o updated_at. Forçado aqui porque, se
            // só a imagem mudou, o save() não teria nada para gravar.
            $photo->touch();
        }

        $photo->save();

        return redirect()
            ->route('admin.fotos.index')
            ->with('sucesso', 'Foto atualizada.');
    }

    public function destroy(int $id)
    {
        $photo = $this->buscarDoOrganizador($id);

        // O caminho no bucket é derivado do id: uma foto futura com o mesmo id
        // herdaria esta imagem se ela ficasse órfã.
        ImagensDaFoto::apagar($photo);

        $photo->delete();

        return redirect()
            ->route('admin.fotos.index')
            ->with('sucesso', 'Foto apagada.');
    }

    /**
     * 404 e não 403 para foto de outro organizador: dizer "existe, mas não é
     * sua" já é contar algo sobre o vizinho.
     */
    private function buscarDoOrganizador(int $id): Photo
    {
        return Photo::where('id', $id)
            ->where('organizer_id', $this->organizerId())
            ->firstOrFail();
    }
}
