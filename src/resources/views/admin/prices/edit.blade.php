@extends('layouts.admin')

@section('titulo', 'Grade de preços')
@section('icone', 'dollar-sign')
@section('subtitulo', $event->title)

@section('conteudo')

    @include('admin._abas-do-evento')

    @php
        // Linhas da grade: cada (modalidade, kit) em que o kit está vinculado à
        // modalidade. O vínculo se faz no formulário do kit.
        $linhas = [];
        foreach ($modalities as $modalidade) {
            foreach ($kits as $kit) {
                if ($kit->modalities->contains('id', $modalidade->id)) {
                    $linhas[] = [$modalidade, $kit];
                }
            }
        }
        $faltas = [];
        if ($modalities->isEmpty()) $faltas[] = ['Modalidades', route('admin.eventos.modalidades.index', $event->id)];
        if ($kits->isEmpty())       $faltas[] = ['Kits', route('admin.eventos.kits.index', $event->id)];
        if ($lots->isEmpty())       $faltas[] = ['Lotes', route('admin.eventos.lotes.index', $event->id)];
    @endphp

    @if ($faltas)
        <div class="card mb-4">
            <div class="card-body p-5 text-center text-muted">
                <p class="mb-2">A grade precisa de modalidades, kits e ao menos um lote para existir.</p>
                <p class="mb-0">Falta cadastrar:
                    @foreach ($faltas as [$nome, $url])
                        <a class="btn btn-sm btn-outline-primary ml-1" href="{{ $url }}">{{ $nome }}</a>
                    @endforeach
                </p>
            </div>
        </div>
    @elseif (empty($linhas))
        <div class="card mb-4">
            <div class="card-body p-5 text-center text-muted">
                <p class="mb-2">Nenhum kit está vinculado a uma modalidade.</p>
                <p class="mb-0">Abra cada kit na aba <a href="{{ route('admin.eventos.kits.index', $event->id) }}">Kits</a> e marque em quais modalidades ele pode ser comprado — só assim ele ganha uma linha aqui.</p>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('admin.eventos.precos.update', $event->id) }}">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $erro)
                        <div>{{ $erro }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0" style="min-width: {{ 420 + 160 * $lots->count() }}px;">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 200px;">Modalidade</th>
                                    <th style="width: 220px;">Kit</th>
                                    @foreach ($lots as $lot)
                                        <th class="text-center">
                                            {{ $lot->name }}
                                            <div class="small font-weight-normal text-muted">{{ $lot->periodoPorExtenso() }}</div>
                                            @if ($event->loteVigente()?->is($lot))
                                                <span class="badge badge-success-soft text-success">vigente</span>
                                            @elseif (! $lot->active)
                                                <span class="badge badge-secondary-soft text-secondary">inativo</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($linhas as [$modalidade, $kit])
                                    <tr>
                                        <td class="font-weight-500 align-middle">{{ $modalidade->name }}</td>
                                        <td class="align-middle">
                                            {{ $kit->name }}
                                            @unless ($kit->active) <span class="badge badge-secondary-soft text-secondary ml-1">inativo</span> @endunless
                                        </td>
                                        @foreach ($lots as $lot)
                                            @php
                                                $chave = "{$modalidade->id}-{$kit->id}-{$lot->id}";
                                                $nome = "precos[{$modalidade->id}][{$kit->id}][{$lot->id}]";
                                                $valor = old("precos.{$modalidade->id}.{$kit->id}.{$lot->id}", $prices[$chave] ?? '');
                                            @endphp
                                            <td class="p-1">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend"><span class="input-group-text">R$</span></div>
                                                    <input class="form-control text-right @error("precos.{$modalidade->id}.{$kit->id}.{$lot->id}") is-invalid @enderror"
                                                           type="number" step="0.01" min="0.01" max="99999.99"
                                                           name="{{ $nome }}" value="{{ $valor }}" placeholder="—"
                                                           {{ $event->jaAconteceu() ? 'disabled' : '' }}>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @unless ($event->jaAconteceu())
                <button class="btn btn-primary" type="submit">Salvar grade</button>
            @endunless
        </form>

        <p class="small text-muted mt-3">
            Célula em branco = a combinação não é vendida naquele lote. A inscrição guarda o preço do momento em que foi feita,
            então mudar aqui não altera quem já se inscreveu. Categorias etárias e cupons abatem depois, sobre o valor desta grade.
        </p>
    @endif

@endsection
