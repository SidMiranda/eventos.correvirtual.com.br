@php $kit = $kit ?? null; @endphp

<div class="form-group">
    <label class="small mb-1" for="name">Nome do kit</label>
    <input class="form-control @error('name') is-invalid @enderror"
           id="name" name="name" type="text" required maxlength="255"
           placeholder="Ex.: Kit Camiseta, Kit Digital"
           value="{{ old('name', $kit?->name) }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="description">O que vem no kit</label>
    <textarea class="form-control @error('description') is-invalid @enderror"
              id="description" name="description" rows="3"
              placeholder="Ex.: Camiseta + medalha + número de peito">{{ old('description', $kit?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="price">Preço (R$)</label>
            <input class="form-control @error('price') is-invalid @enderror"
                   id="price" name="price" type="number" step="0.01" min="0.01" required
                   placeholder="Ex.: 79.90"
                   value="{{ old('price', $kit?->price) }}">
            @error('price')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">
                    É este o valor cobrado no Pix. A inscrição guarda o preço do momento em que foi criada,
                    então mudar aqui não altera quem já se inscreveu.
                </small>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="stock">Estoque</label>
            <input class="form-control @error('stock') is-invalid @enderror"
                   id="stock" name="stock" type="number" min="0"
                   placeholder="Deixe em branco para não controlar"
                   value="{{ old('stock', $kit?->stock) }}">
            @error('stock')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">O sistema ainda não bloqueia a venda ao zerar — hoje é só informativo.</small>
            @enderror
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Em quais modalidades este kit vale</h6>

@php
    $modalidadesDoEvento = $event->modalities()->orderBy('distance_km')->orderBy('name')->get();
    $marcadas = collect(old('modalidades', $kit?->modalities?->pluck('id')->all() ?? []))->map(fn ($v) => (int) $v);
@endphp

@if ($modalidadesDoEvento->isEmpty())
    <p class="small text-muted">
        Este evento ainda não tem modalidades. Cadastre-as na aba "Modalidades" e volte aqui para marcar
        em quais o kit pode ser comprado — sem isso o kit não aparece para o atleta.
    </p>
@else
    <div class="form-group">
        @foreach ($modalidadesDoEvento as $modalidade)
            <div class="custom-control custom-checkbox">
                <input class="custom-control-input @error('modalidades') is-invalid @enderror" type="checkbox"
                       id="modalidade_{{ $modalidade->id }}" name="modalidades[]" value="{{ $modalidade->id }}"
                       @checked($marcadas->contains($modalidade->id))>
                <label class="custom-control-label" for="modalidade_{{ $modalidade->id }}">{{ $modalidade->name }}</label>
            </div>
        @endforeach
        @error('modalidades')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @else
            <small class="form-text text-muted">O atleta só vê este kit nas modalidades marcadas — é o que impede escolher o 5K e levar o kit do 10K.</small>
        @enderror
    </div>
@endif

<hr class="my-4">
<h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Tamanhos de camiseta</h6>

@php $tamanhosMarcados = collect(old('tamanhos', $kit?->tamanhos() ?? [])); @endphp

<div class="form-group">
    <div class="row">
        @foreach (['Camiseta' => \App\Models\Subscription::CAMISETAS, 'Baby look' => \App\Models\Subscription::CAMISETAS_BABY_LOOK] as $grupo => $tabela)
            <div class="col-md-6">
                <div class="small text-muted mb-2">{{ $grupo }}</div>
                @foreach ($tabela as $codigo => $medida)
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" type="checkbox" id="tam_{{ $codigo }}" name="tamanhos[]" value="{{ $codigo }}"
                               @checked($tamanhosMarcados->contains($codigo))>
                        <label class="custom-control-label" for="tam_{{ $codigo }}">{{ $codigo }} <span class="text-muted">— {{ $medida }}</span></label>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    @error('tamanhos.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Marque os tamanhos que este kit oferece. <strong>Kit sem camiseta: deixe tudo desmarcado</strong> — o atleta não é
            perguntado. Com tamanhos marcados, escolher um passa a ser obrigatório na inscrição.
        </small>
    @enderror
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="active" value="0">
        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
               {{ old('active', $kit?->active ?? true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="active">Kit ativo</label>
    </div>
    <small class="form-text text-muted">Só kits ativos aparecem para o atleta escolher.</small>
</div>
