@extends('layouts.admin')

@section('titulo', $atleta->name)
@section('icone', 'user')
@section('subtitulo', 'Ficha do atleta')

@section('acoes')
    <a class="btn btn-primary" href="{{ route('admin.atletas.index') }}">
        <i class="mr-1" data-feather="arrow-left"></i> Voltar aos atletas
    </a>
@endsection

@section('conteudo')

    <div class="row">
        <div class="col-xl-4 mb-4">
            <div class="card h-100">
                <div class="card-header">Cadastro</div>
                <div class="card-body">
                    @php
                        $sexo = ['male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro'];
                        $campos = [
                            'E-mail' => $atleta->email,
                            'CPF' => $atleta->cpf,
                            'Telefone' => $atleta->phone,
                            'Nascimento' => $atleta->birth_date ? \Carbon\Carbon::parse($atleta->birth_date)->format('d/m/Y') : null,
                            'Sexo' => $sexo[$atleta->sex] ?? null,
                            'Cidade' => $atleta->city?->nomeCompleto(),
                            // Só aparece para quem se cadastrou menor de idade:
                            // é quem assina por ele.
                            'CPF do responsável' => $atleta->guardian_cpf,
                            'Cadastrado em' => $atleta->created_at?->format('d/m/Y'),
                        ];
                    @endphp

                    @foreach ($campos as $rotulo => $valor)
                        <div class="mb-3">
                            <div class="small text-muted">{{ $rotulo }}</div>
                            <div>{{ $valor ?: '—' }}</div>
                        </div>
                    @endforeach

                    {{-- Só consulta: o organizador não edita cadastro de outra
                         pessoa. Quem muda e-mail, telefone ou senha é o próprio
                         atleta, pela conta dele. --}}
                    <p class="small text-muted mb-0">
                        Estes dados são do cadastro do atleta e só ele pode alterá-los.
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    Inscrições nos seus eventos
                    <span class="badge badge-secondary-soft text-secondary ml-1">{{ $inscricoes->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @if ($inscricoes->isEmpty())
                        <div class="p-4 text-center text-muted small">
                            Nenhuma inscrição nos seus eventos.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Evento</th>
                                        <th>Modalidade / kit</th>
                                        <th>Equipe</th>
                                        <th class="text-right">Valor</th>
                                        <th class="text-center">Situação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($inscricoes as $inscricao)
                                        <tr>
                                            <td>
                                                <div>{{ $inscricao->event->title }}</div>
                                                <div class="small text-muted">{{ $inscricao->event->event_date?->format('d/m/Y') }}</div>
                                            </td>
                                            <td>
                                                <div>{{ $inscricao->modality->name ?? '—' }}</div>
                                                <div class="small text-muted">{{ $inscricao->kit->name ?? '—' }}</div>
                                            </td>
                                            <td class="small">{{ $inscricao->team_name ?: '—' }}</td>
                                            <td class="text-right">
                                                <div class="font-weight-500">R$ {{ number_format($inscricao->price, 2, ',', '.') }}</div>
                                                @if ($inscricao->temDesconto())
                                                    <div class="small text-success">
                                                        {{ $inscricao->coupon?->code ?? 'cupom' }}:
                                                        −R$ {{ number_format($inscricao->discount_amount, 2, ',', '.') }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-{{ $inscricao->corDaSituacao() }}-soft text-{{ $inscricao->corDaSituacao() }}">
                                                    {{ $inscricao->situacao() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection
