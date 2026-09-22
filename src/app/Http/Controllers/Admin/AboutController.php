<?php

namespace App\Http\Controllers\Admin;

use App\Support\ImagensDoSobre;
use Illuminate\Http\Request;

/**
 * O bloco "Sobre nós" da home do organizador.
 *
 * Registro único: é uma seção do site, não uma lista. Por isso só `edit` e
 * `update` — não há o que criar nem o que apagar, e o escopo é o organizador
 * do usuário logado, como no resto do painel.
 *
 * Até 2026-09-22 todo esse conteúdo era texto fixo no Blade, igual em todos os
 * sites da plataforma (ver a migration que trouxe as colunas).
 */
class AboutController extends AdminController
{
    public function edit()
    {
        return view('admin.about.edit', ['organizador' => $this->organizer()]);
    }

    public function update(Request $request)
    {
        $organizador = $this->organizer();

        $dados = $request->validate([
            'about_badge' => ['nullable', 'string', 'max:60'],
            'about_title' => ['nullable', 'string', 'max:120'],
            'about_text' => ['nullable', 'string', 'max:5000'],
            'about_button_label' => ['nullable', 'string', 'max:40'],
            // Precisa de esquema: sem `https://` o navegador lê o valor como
            // caminho relativo e o botão leva para dentro do próprio site.
            // Mesma regra do site do patrocinador.
            'about_button_url' => ['nullable', 'url', 'starts_with:http://,https://', 'max:255'],
            'foto' => ImagensDoSobre::regraDeValidacao(),
            'apagar_foto' => ['boolean'],
        ], [
            'about_button_url.url' => 'O link do botão precisa ser um endereço completo.',
            'about_button_url.starts_with' => 'O link do botão precisa começar com https://.',
            'foto.image' => 'A foto precisa ser uma imagem (JPG, PNG ou WEBP).',
            'foto.max' => 'A foto passou de 5 MB.',
        ]);

        unset($dados['foto'], $dados['apagar_foto']);

        $organizador->fill($dados)->save();

        if ($request->boolean('apagar_foto')) {
            ImagensDoSobre::apagar($organizador);
            $organizador->touch();
        } elseif ($request->hasFile('foto')) {
            ImagensDoSobre::salvar($organizador, $request->file('foto'));

            // O `touch` é o que move o `?v=` da URL (ver
            // Arquivos::sobreNosDoOrganizador). Sem ele, quem troca só a foto
            // sem mexer no texto não mudaria `updated_at` — o save seria um
            // no-op — e o CDN continuaria servindo a imagem antiga por um ano.
            $organizador->touch();
        }

        return redirect()
            ->route('admin.sobre.edit')
            ->with('sucesso', 'Bloco "Sobre nós" atualizado.');
    }
}
