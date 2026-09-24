@php $category = $category ?? null; @endphp

<div class="form-group">
    <label class="small mb-1" for="name">Nome da categoria</label>
    <input class="form-control @error('name') is-invalid @enderror"
           id="name" name="name" type="text" required maxlength="255"
           placeholder="Ex.: Criança, Idoso, PCD"
           value="{{ old('name', $category?->name) }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="min_age">Idade mínima</label>
            <input class="form-control @error('min_age') is-invalid @enderror"
                   id="min_age" name="min_age" type="number" min="0" max="120"
                   placeholder="Em branco = sem mínimo"
                   value="{{ old('min_age', $category?->min_age) }}">
            @error('min_age') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="max_age">Idade máxima</label>
            <input class="form-control @error('max_age') is-invalid @enderror"
                   id="max_age" name="max_age" type="number" min="0" max="120"
                   placeholder="Em branco = sem máximo"
                   value="{{ old('max_age', $category?->max_age) }}">
            @error('max_age')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Limites inclusivos: "até 12" pega quem tem 12. "Idoso" é mínimo 60 e máximo em branco.</small>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="discount_type">Tipo de desconto</label>
            @php $tipo = old('discount_type', $category?->discount_type ?? \App\Models\AgeCategory::TIPO_PERCENTUAL); @endphp
            <select class="form-control @error('discount_type') is-invalid @enderror" id="discount_type" name="discount_type" required>
                <option value="{{ \App\Models\AgeCategory::TIPO_PERCENTUAL }}" @selected($tipo === \App\Models\AgeCategory::TIPO_PERCENTUAL)>Percentual (%)</option>
                <option value="{{ \App\Models\AgeCategory::TIPO_VALOR }}" @selected($tipo === \App\Models\AgeCategory::TIPO_VALOR)>Valor fixo (R$)</option>
            </select>
            @error('discount_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="discount_value">Desconto</label>
            <input class="form-control @error('discount_value') is-invalid @enderror"
                   id="discount_value" name="discount_value" type="number" step="0.01" min="0.01" required
                   placeholder="Ex.: 50 ou 25.00"
                   value="{{ old('discount_value', $category?->discount_value) }}">
            @error('discount_value')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Incide sobre o preço da grade. Se o atleta também usar cupom, o cupom abate o que sobrou depois deste desconto.</small>
            @enderror
        </div>
    </div>
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="active" value="0">
        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
               {{ old('active', $category?->active ?? true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="active">Categoria ativa</label>
    </div>
    <small class="form-text text-muted">Categoria inativa não dá desconto. Se o atleta cair em mais de uma, vale a de maior desconto em reais.</small>
</div>
