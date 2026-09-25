{{-- Campos compartilhados por criar e editar. $event vem preenchido na edição
     e nulo na criação; old() sempre vence, pra não perder o que foi digitado
     quando a validação recusa o formulário. --}}

@php
    $event = $event ?? null;

    $valorData = function ($campo) use ($event) {
        $valor = old($campo, $event?->{$campo});
        if (!$valor) {
            return '';
        }
        return $valor instanceof \DateTimeInterface
            ? $valor->format('Y-m-d\TH:i')
            : \Illuminate\Support\Carbon::parse($valor)->format('Y-m-d\TH:i');
    };
@endphp

<div class="form-group">
    <label class="small mb-1" for="title">Nome do evento</label>
    <input class="form-control @error('title') is-invalid @enderror"
           id="title" name="title" type="text" required maxlength="255"
           placeholder="Ex.: 5ª Corrida de Mogi Guaçu"
           value="{{ old('title', $event?->title) }}">
    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="description">Descrição</label>
    <textarea class="form-control @error('description') is-invalid @enderror"
              id="description" name="description" rows="4" required
              placeholder="O que o atleta precisa saber sobre o evento">{{ old('description', $event?->description) }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="location">Local</label>
    <input class="form-control @error('location') is-invalid @enderror"
           id="location" name="location" type="text" required maxlength="255"
           placeholder="Ex.: Parque dos Ingás, Mogi Guaçu — SP"
           value="{{ old('location', $event?->location) }}">
    @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="event_date">Data e hora do evento</label>
            <input class="form-control @error('event_date') is-invalid @enderror"
                   id="event_date" name="event_date" type="datetime-local" required
                   value="{{ $valorData('event_date') }}">
            @error('event_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="registration_deadline">Inscrições até</label>
            <input class="form-control @error('registration_deadline') is-invalid @enderror"
                   id="registration_deadline" name="registration_deadline" type="datetime-local" required
                   value="{{ $valorData('registration_deadline') }}">
            @error('registration_deadline')
                <div class="invalid-feedback">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Precisa ser antes (ou no mesmo momento) da data do evento.</small>
            @enderror
        </div>
    </div>
</div>

<hr class="my-4">
<h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Imagens do evento</h6>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="banner">Banner (topo da página do evento)</label>

            @if ($event?->banner_url)
                <div class="mb-2">
                    <img src="{{ \App\Support\Arquivos::bannerDoEvento($event) }}"
                         alt="Banner atual" class="img-fluid rounded border" style="max-height: 110px;"
                         onerror="this.style.display='none';">
                </div>
            @endif

            <input class="form-control-file @error('banner') is-invalid @enderror"
                   id="banner" name="banner" type="file" accept="image/jpeg,image/png,image/webp">
            @error('banner')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @else
                <small class="form-text text-muted">
                    <strong>Imagem horizontal</strong> — algo como 1600x320. Ela vira o topo da página do evento.
                    Se você enviar o cartaz em pé aqui, o topo continua no degradê azul (cartaz retrato fica
                    recortado no nome e na data); o cartaz é o campo ao lado. JPG, PNG ou WEBP, até 5 MB.
                </small>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label class="small mb-1" for="card">Card (imagem da listagem)</label>

            @if ($event?->banner_url)
                <div class="mb-2">
                    <img src="{{ \App\Support\Arquivos::cardDoEvento($event) }}"
                         alt="Card atual" class="img-fluid rounded border" style="max-height: 110px;"
                         onerror="this.style.display='none';">
                </div>
            @endif

            <input class="form-control-file @error('card') is-invalid @enderror"
                   id="card" name="card" type="file" accept="image/jpeg,image/png,image/webp">
            @error('card')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @else
                <small class="form-text text-muted">Mais quadrada, aparece na lista de eventos.</small>
            @enderror
        </div>
    </div>
</div>

<p class="small text-muted">
    As imagens vão para o armazenamento externo, não para dentro do servidor — assim não se perdem numa
    atualização do sistema. Deixe em branco para manter as que já estão lá.
</p>

<div class="form-group">
    <label class="small mb-1" for="accent_color">Cor do evento</label>

    <div class="d-flex align-items-center">
        <input class="form-control @error('accent_color') is-invalid @enderror mr-3"
               id="accent_color" name="accent_color" type="color" style="width: 68px; height: 40px; padding: 4px;"
               value="{{ old('accent_color', $event?->accent_color ?? \App\Models\Event::COR_PADRAO) }}">

        <div class="flex-grow-1 rounded"
             style="height: 40px; background: {{ $event?->degrade() ?? 'linear-gradient(135deg, #05080d 0%, ' . \App\Models\Event::COR_PADRAO . ' 58%, #05080d 100%)' }};"></div>
    </div>

    @error('accent_color')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Usada quando o evento não tem imagem: o card e o topo da página viram um degradê escuro
            puxado para esta cor, com o nome do evento por cima. A faixa ao lado mostra como fica.
        </small>
    @enderror
</div>

<hr class="my-4">
<h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Cronograma da prova</h6>

<div class="form-group">
    <label class="small mb-1" for="schedule">Programação</label>
    <textarea class="form-control @error('schedule') is-invalid @enderror"
              id="schedule" name="schedule" rows="7"
              placeholder="04h - Abertura do estacionamento&#10;05h30 - Largada 10km&#10;06h - Largada 5km&#10;08h30 - Premiação">{{ old('schedule', $event?->schedule) }}</textarea>
    @error('schedule')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Um horário por linha, como você escrever aqui — a quebra de linha e a linha em branco
            aparecem iguais na página do evento. Deixe em branco se a programação ainda não fechou:
            o bloco simplesmente não aparece.
        </small>
    @enderror
</div>

<div class="form-group">
    <label class="small mb-1" for="registration_info">Informações da inscrição</label>
    <textarea class="form-control @error('registration_info') is-invalid @enderror"
              id="registration_info" name="registration_info" rows="5"
              placeholder="O que a inscrição inclui, como retirar o kit, troca de tamanho de camiseta...">{{ old('registration_info', $event?->registration_info) }}</textarea>
    @error('registration_info')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Aparece no bloco "Inscrição" da página do evento, com as quebras de linha que você usar.
            A data de encerramento das inscrições sai sozinha logo abaixo — não precisa repetir aqui.
        </small>
    @enderror
</div>

{{-- Até quando o atleta troca camiseta e equipe pela "Minha conta"
     (docs/specs/area-do-atleta.md). Vazio = o encerramento das inscrições. --}}
<div class="form-group">
    <label class="small mb-1" for="changes_deadline">Alterações até <span class="text-muted">(opcional)</span></label>
    <input class="form-control @error('changes_deadline') is-invalid @enderror" style="max-width: 280px;"
           id="changes_deadline" name="changes_deadline" type="datetime-local"
           value="{{ $valorData('changes_deadline') }}">
    @error('changes_deadline')
        <div class="invalid-feedback">{{ $message }}</div>
    @else
        <small class="form-text text-muted">
            Até quando o atleta pode trocar o tamanho da camiseta e a equipe pela área dele.
            Em branco, vale a data de encerramento das inscrições.
        </small>
    @enderror
</div>

<hr class="my-4">
<h6 class="text-muted mb-3" style="letter-spacing:.06em; text-transform:uppercase; font-size:12px;">Categorias etárias</h6>

<div class="form-group">
    <label class="small mb-1 d-block">Como contar a idade do atleta</label>
    @php $criterio = old('age_criteria', $event?->age_criteria ?? \App\Models\Event::CRITERIO_ANO_CALENDARIO); @endphp
    <div class="custom-control custom-radio">
        <input class="custom-control-input" type="radio" id="criterio_ano" name="age_criteria"
               value="{{ \App\Models\Event::CRITERIO_ANO_CALENDARIO }}" @checked($criterio === \App\Models\Event::CRITERIO_ANO_CALENDARIO)>
        <label class="custom-control-label" for="criterio_ano">
            <strong>Ano-calendário</strong> — ano do evento menos ano de nascimento. Quem faz 60 em dezembro já conta 60 em janeiro (critério das federações).
        </label>
    </div>
    <div class="custom-control custom-radio mt-2">
        <input class="custom-control-input" type="radio" id="criterio_data" name="age_criteria"
               value="{{ \App\Models\Event::CRITERIO_DATA_EXATA }}" @checked($criterio === \App\Models\Event::CRITERIO_DATA_EXATA)>
        <label class="custom-control-label" for="criterio_data">
            <strong>Data exata</strong> — anos completos no dia da prova.
        </label>
    </div>
    <small class="form-text text-muted">
        Vale para as categorias etárias com desconto (aba "Categorias"). A data de nascimento vem do cadastro do atleta.
    </small>
</div>

<div class="form-group">
    <div class="custom-control custom-switch">
        <input type="hidden" name="active" value="0">
        <input class="custom-control-input" id="active" name="active" type="checkbox" value="1"
               {{ old('active', $event?->active ?? true) ? 'checked' : '' }}>
        <label class="custom-control-label" for="active">Evento ativo</label>
    </div>
    <small class="form-text text-muted">Só eventos ativos aparecem no site para os atletas.</small>
</div>
