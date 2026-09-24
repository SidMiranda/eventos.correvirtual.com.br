@php
    $lot = $lot ?? null;

    $valorData = function ($campo) use ($lot) {
        $valor = old($campo, $lot?->{$campo});
        if (!$valor) {
            return '';
        }
        return $valor instanceof \DateTimeInterface
            ? $valor->format('Y-m-d\TH:i')
            : \Illuminate\Support\Carbon::parse($valor)->format('Y-m-d\TH:i');
    };
@endphp

<div class="form-group">
    <label class="small mb-1" for="name">Nome do lote</label>
    <input class="form-control @error('name') is-invalid @enderror"
           id="name" name="name" type="text" required maxlength="255"
           placeholder="Ex.: Lote 1, Lote promocional, Último lote"
           value="{{ old('name', $lot?->name) }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="starts_at">Começa em</label>
            <input class="form-control @error('starts_at') is-invalid @enderror"
                   id="starts_at" name="starts_at" type="datetime-local" required
                   value="{{ $valorData('starts_at') }}">
            @error('starts_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="ends_at">Termina em</label>
            <input class="form-control @error('ends_at') is-invalid @enderror"
                   id="ends_at" name="ends_at" type="datetime-local"
                   value="{{ $valorData('ends_at') }}">
            @error('ends_at')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Deixe em branco para o lote valer até o fim das inscrições.</small>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="max_subscriptions">Limite de inscrições</label>
            <input class="form-control @error('max_subscriptions') is-invalid @enderror"
                   id="max_subscriptions" name="max_subscriptions" type="number" min="1"
                   placeholder="Deixe em branco para não limitar"
                   value="{{ old('max_subscriptions', $lot?->max_subscriptions) }}">
            @error('max_subscriptions')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Ao chegar nesse número, o lote vira sozinho para o próximo. Inscrições canceladas não contam.</small>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="position">Ordem</label>
            <input class="form-control @error('position') is-invalid @enderror"
                   id="position" name="position" type="number" min="0" max="999"
                   value="{{ old('position', $lot?->position ?? 0) }}">
            @error('position')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Se dois lotes valerem ao mesmo tempo, o de menor ordem vende. Também é a ordem das colunas na grade.</small>
            @enderror
        </div>
    </div>
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="active" value="0">
        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
               {{ old('active', $lot?->active ?? true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="active">Lote ativo</label>
    </div>
    <small class="form-text text-muted">Lote inativo não vende, mesmo dentro da janela. Serve para tirar um lote do ar sem apagar a coluna de preços.</small>
</div>
