{{-- Só a edição usa este formulário: o cadastro é em lote (create.blade.php). --}}

<div class="form-group">
    <label class="small mb-1" for="foto">Imagem</label>

    <div class="d-flex align-items-center">
        <img src="{{ \App\Support\Arquivos::fotoDaGaleria($photo) }}"
             alt="{{ $photo->caption }}" class="foto-painel foto-painel--grande mr-3"
             onerror="this.style.display='none';">

        <input class="form-control-file @error('foto') is-invalid @enderror"
               id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
    </div>

    @error('foto')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Deixe em branco para manter a atual. A nova também vira um quadrado recortado pelo centro. Até 5 MB.
        </small>
    @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="caption">Legenda</label>
    <input class="form-control @error('caption') is-invalid @enderror"
           id="caption" name="caption" type="text" maxlength="160"
           placeholder="Ex.: Largada da 2ª Corrida pela Vida"
           value="{{ old('caption', $photo->caption) }}">
    @error('caption')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Opcional. Não aparece escrita na galeria — serve de descrição da imagem (leitores de tela, busca) e de dica ao passar o mouse.
        </small>
    @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="link_url">Link</label>
    <input class="form-control @error('link_url') is-invalid @enderror"
           id="link_url" name="link_url" type="url" maxlength="255"
           placeholder="https://www.instagram.com/p/…"
           value="{{ old('link_url', $photo->link_url) }}">
    @error('link_url')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Opcional. Com link, a foto abre esse endereço em aba nova — o post no Instagram, por exemplo.
            Precisa começar com <strong>https://</strong>.
        </small>
    @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="position">Ordem</label>
    <input class="form-control @error('position') is-invalid @enderror"
           id="position" name="position" type="number" min="0" max="999" style="max-width: 140px;"
           value="{{ old('position', $photo->position) }}">
    @error('position')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Menor aparece primeiro. Quem tiver o mesmo número entra pela mais nova.
        </small>
    @enderror
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="active" value="0">
        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
               {{ old('active', $photo->active) ? 'checked' : '' }}>
        <label class="custom-control-label" for="active">Aparece no site</label>
    </div>
    <small class="form-text text-muted">
        Desative para tirar da galeria sem apagar.
    </small>
</div>
