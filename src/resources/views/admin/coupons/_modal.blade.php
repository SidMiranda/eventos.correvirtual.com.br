{{-- Um modal só, reaproveitado para criar e para editar.

     Quem preenche os campos é o JS de index.blade.php, a partir do cupom
     clicado. A exceção é quando o servidor recusou a validação: aí os campos
     já vêm com old() do Blade, e o JS apenas reabre o modal sem sobrescrever
     o que a pessoa tinha digitado. --}}

<div class="modal fade" id="modalCupom" tabindex="-1" role="dialog" aria-labelledby="tituloModalCupom" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">

            <form method="POST" id="formCupom" action="{{ route('admin.cupons.store') }}">
                @csrf
                {{-- Preenchidos pelo JS ao abrir em modo edição. --}}
                <input type="hidden" name="_method" id="cupom_method" value="POST">
                <input type="hidden" name="cupom_id" id="cupom_id" value="{{ old('cupom_id') }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="tituloModalCupom">Criar cupom</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Fechar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">

                    {{-- Aviso de campos congelados: só aparece na edição de um
                         cupom que já foi usado. --}}
                    <div class="alert alert-warning alert-icon d-none" id="avisoCupomUsado" role="alert">
                        <div class="alert-icon-aside"><i class="fas fa-lock"></i></div>
                        <div class="alert-icon-content small">
                            Este cupom já foi usado. O <strong>código</strong> e o <strong>evento</strong>
                            ficam congelados — trocá-los reescreveria o desconto que alguém já recebeu.
                            O resto continua editável.
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label class="small mb-1" for="event_id">Evento</label>
                            <select class="form-control @error('event_id') is-invalid @enderror"
                                    id="event_id" name="event_id" required>
                                <option value="">Escolha o evento…</option>
                                @foreach ($eventosParaCupom as $evento)
                                    <option value="{{ $evento->id }}" @selected(old('event_id') == $evento->id)>
                                        {{ $evento->title }} ({{ $evento->event_date?->format('d/m/Y') }})
                                    </option>
                                @endforeach
                            </select>
                            @error('event_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">
                                    O cupom vale só neste evento. Provas que já aconteceram não aparecem aqui.
                                </small>
                            @enderror
                        </div>

                        <div class="form-group col-md-5">
                            <label class="small mb-1" for="code">Código</label>
                            <input class="form-control text-uppercase @error('code') is-invalid @enderror"
                                   id="code" name="code" type="text" required
                                   maxlength="7" minlength="6" autocomplete="off" spellcheck="false"
                                   placeholder="Ex.: CORRE10"
                                   value="{{ old('code') }}">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted" id="dicaCodigo">
                                    6 ou 7 caracteres, letras e números.
                                </small>
                            @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label class="small mb-1" for="discount_type">Tipo de desconto</label>
                            <select class="form-control @error('discount_type') is-invalid @enderror"
                                    id="discount_type" name="discount_type" required>
                                <option value="{{ \App\Models\Coupon::TIPO_PERCENTUAL }}"
                                    @selected(old('discount_type', \App\Models\Coupon::TIPO_PERCENTUAL) === \App\Models\Coupon::TIPO_PERCENTUAL)>
                                    Porcentagem (%)
                                </option>
                                <option value="{{ \App\Models\Coupon::TIPO_VALOR }}"
                                    @selected(old('discount_type') === \App\Models\Coupon::TIPO_VALOR)>
                                    Valor em reais (R$)
                                </option>
                            </select>
                            @error('discount_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="form-group col-md-3">
                            <label class="small mb-1" for="discount_value">Desconto</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" id="prefixoDesconto">%</span>
                                </div>
                                <input class="form-control @error('discount_value') is-invalid @enderror"
                                       id="discount_value" name="discount_value" type="number"
                                       step="0.01" min="0.01" required
                                       value="{{ old('discount_value') }}">
                                @error('discount_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <small class="form-text text-muted" id="dicaDesconto">De 1 a 100.</small>
                        </div>

                        <div class="form-group col-md-2">
                            <label class="small mb-1" for="total_quantity">Quantidade</label>
                            <input class="form-control @error('total_quantity') is-invalid @enderror"
                                   id="total_quantity" name="total_quantity" type="number"
                                   min="1" step="1" required
                                   value="{{ old('total_quantity') }}">
                            @error('total_quantity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">Usos.</small>
                            @enderror
                        </div>

                        <div class="form-group col-md-3">
                            <label class="small mb-1" for="expires_at">Expira em</label>
                            <input class="form-control @error('expires_at') is-invalid @enderror"
                                   id="expires_at" name="expires_at" type="date" required
                                   value="{{ old('expires_at') }}">
                            @error('expires_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <small class="form-text text-muted">Vale o dia todo.</small>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="small mb-1" for="description">Descrição</label>
                        <textarea class="form-control @error('description') is-invalid @enderror"
                                  id="description" name="description" rows="2" maxlength="500"
                                  placeholder="Opcional — para que serve este cupom, com quem foi combinado">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <small class="form-text text-muted">Só para você. O atleta não vê.</small>
                        @enderror
                    </div>

                    <div class="form-group mb-0">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="active" value="0">
                            <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
                                   {{ old('active', true) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="active">Cupom ativo</label>
                        </div>
                        <small class="form-text text-muted">
                            Desative para tirar de circulação sem apagar — o histórico de uso fica.
                        </small>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-link text-muted" type="button" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit" id="botaoSalvarCupom">Criar cupom</button>
                </div>
            </form>

        </div>
    </div>
</div>
