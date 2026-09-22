@extends('layouts.admin')

@section('titulo', 'Sobre nós')
@section('icone', 'info')
@section('subtitulo', 'O bloco de apresentação na home do seu site')

@section('conteudo')
    <div class="row justify-content-center">
        <div class="col-xl-9">

            <div class="card mb-4">
                <div class="card-header">Conteúdo do bloco</div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.sobre.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label class="small mb-1" for="about_badge">Tag</label>
                            <input class="form-control @error('about_badge') is-invalid @enderror"
                                   id="about_badge" name="about_badge" type="text" maxlength="60"
                                   placeholder="Ex.: SOBRE A PLATAFORMA"
                                   value="{{ old('about_badge', $organizador->about_badge) }}">
                            @error('about_badge')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">A linha pequena com a bolinha verde, acima do título.</small>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="small mb-1" for="about_title">Título</label>
                            <input class="form-control @error('about_title') is-invalid @enderror"
                                   id="about_title" name="about_title" type="text" maxlength="120"
                                   placeholder="Ex.: Corre Virtual - Desafie seus limites"
                                   value="{{ old('about_title', $organizador->about_title) }}">
                            @error('about_title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group">
                            <label class="small mb-1" for="about_text">Texto</label>
                            <textarea class="form-control @error('about_text') is-invalid @enderror"
                                      id="about_text" name="about_text" rows="10"
                                      placeholder="Quem vocês são, o que a plataforma oferece, por que alguém deveria correr com vocês.">{{ old('about_text', $organizador->about_text) }}</textarea>
                            @error('about_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">
                                    Deixe uma linha em branco entre um parágrafo e outro. Para destacar uma
                                    palavra, escreva <code>**assim**</code> — ela sai em negrito no site.
                                </small>
                            @enderror
                        </div>

                        <hr class="my-4">
                        <h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Botão</h6>

                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label class="small mb-1" for="about_button_label">Texto do botão</label>
                                    <input class="form-control @error('about_button_label') is-invalid @enderror"
                                           id="about_button_label" name="about_button_label" type="text" maxlength="40"
                                           placeholder="Ex.: COMEÇAR MEU DESAFIO"
                                           value="{{ old('about_button_label', $organizador->about_button_label) }}">
                                    @error('about_button_label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group">
                                    <label class="small mb-1" for="about_button_url">Link do botão</label>
                                    <input class="form-control @error('about_button_url') is-invalid @enderror"
                                           id="about_button_url" name="about_button_url" type="url" maxlength="255"
                                           placeholder="https://..."
                                           value="{{ old('about_button_url', $organizador->about_button_url) }}">
                                    @error('about_button_url')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <small class="form-text text-muted">
                                            Precisa do <strong>https://</strong>. Sem link, o botão não aparece no site.
                                        </small>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Foto</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="small mb-1" for="foto">Trocar a foto</label>
                                    <input class="form-control-file @error('foto') is-invalid @enderror"
                                           id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                                    @error('foto')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @else
                                        <small class="form-text text-muted">
                                            Ocupa a metade direita do bloco e é recortada pelo centro para preencher.
                                            Algo em pé ou quadrado funciona melhor que uma imagem bem larga.
                                            JPG, PNG ou WEBP, até 5 MB.
                                        </small>
                                    @enderror

                                    <div class="custom-control custom-checkbox mt-3">
                                        <input type="hidden" name="apagar_foto" value="0">
                                        <input class="custom-control-input" id="apagar_foto" name="apagar_foto" type="checkbox" value="1">
                                        <label class="custom-control-label" for="apagar_foto">Remover a foto atual</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <p class="small text-muted mb-2">Foto atual</p>
                                {{-- O organizador não tem no banco um campo dizendo quais imagens possui
                                     (ver Arquivos): assume-se que existe e o `onerror` cobre o caso de não
                                     haver nenhuma. --}}
                                <img src="{{ \App\Support\Arquivos::sobreNosDoOrganizador($organizador) }}"
                                     alt="Foto do bloco Sobre nós" class="img-fluid rounded border"
                                     style="max-height: 220px;"
                                     onerror="this.style.display='none'; document.getElementById('sem-foto').style.display='block';">
                                <p class="small text-muted" id="sem-foto" style="display: none;">Nenhuma foto enviada ainda.</p>
                            </div>
                        </div>

                        <hr class="my-4">
                        <button class="btn btn-primary" type="submit">Salvar alterações</button>
                        <a class="btn btn-link" href="{{ route('admin.dashboard') }}">Cancelar</a>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Como fica no site</div>
                <div class="card-body">
                    <p class="small text-muted mb-0">
                        O bloco é a última seção da home, com o título <strong>SOBRE NÓS</strong> acima dele:
                        texto de um lado, foto do outro, lado a lado no computador e um embaixo do outro no
                        celular. <strong>Sem título e texto preenchidos, a seção inteira não aparece.</strong>
                        <a href="{{ $organizador->siteUrl() }}" target="_blank" rel="noopener">Ver o site</a>.
                    </p>
                </div>
            </div>

        </div>
    </div>
@endsection
