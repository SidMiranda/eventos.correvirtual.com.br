@extends('layouts.app')

@section('title', 'Meu perfil - Corre Virtual')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/top-bar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/minha-conta.css') }}?v={{ filemtime(public_path('css/minha-conta.css')) }}">
@endpush

@php
    $sexo = ['male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro'];
    $formatarCpf = fn (?string $c) => $c && strlen($c) === 11
        ? substr($c, 0, 3) . '.' . substr($c, 3, 3) . '.' . substr($c, 6, 3) . '-' . substr($c, 9)
        : $c;
    $telefone = old('phone', $atleta->phone);
    $digitos = preg_replace('/\D/', '', (string) $telefone);
    if (strlen($digitos) >= 10) {
        $telefone = '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, -4) . '-' . substr($digitos, -4);
    }
@endphp

@section('content')
    <main class="conta">
        <h1 class="conta__titulo">Minha conta</h1>
        @include('conta._abas')

        <x-app.response-message />

        <form method="POST" action="{{ route('conta.perfil') }}" class="conta__form" novalidate>
            @csrf
            @method('PUT')

            <div class="campo">
                <label for="name">Nome</label>
                <input id="name" name="name" type="text" autocomplete="name" maxlength="255" required
                       value="{{ old('name', $atleta->name) }}">
            </div>

            <div class="campo">
                <label for="phone">Celular</label>
                <input id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" required
                       placeholder="(19) 99999-9999" value="{{ $telefone }}">
            </div>

            <div class="campo">
                <label for="campoCidade">Cidade</label>
                <x-campo-cidade :texto="old('cidade', $atleta->city?->nomeCompleto())"
                                :cidade-id="old('city_id', $atleta->city_id)" />
            </div>

            <label class="campo-check">
                <input type="checkbox" name="is_pcd" value="1" @checked(old('is_pcd', $atleta->is_pcd))>
                <span>Sou PCD (pessoa com deficiência)</span>
            </label>

            <button type="submit" class="botao botao--primario">Salvar</button>
        </form>

        {{-- Travados por decisão do dono (2026-09-25). Aparecem para o atleta
             conferir, sem campo: só PerfilDoAtletaRequest decide o que muda. --}}
        <section class="conta__fixos" aria-labelledby="titulo-fixos">
            <h2 class="conta__subtitulo" id="titulo-fixos">Dados que não mudam por aqui</h2>
            <dl>
                <div><dt>E-mail</dt><dd>{{ $atleta->email }}</dd></div>
                <div><dt>CPF</dt><dd>{{ $formatarCpf($atleta->cpf) ?: '—' }}</dd></div>
                <div><dt>Nascimento</dt><dd>{{ $atleta->birth_date ? \Carbon\Carbon::parse($atleta->birth_date)->format('d/m/Y') : '—' }}</dd></div>
                <div><dt>Sexo</dt><dd>{{ $sexo[$atleta->sex] ?? '—' }}</dd></div>
                @if ($atleta->guardian_cpf)
                    <div><dt>CPF do responsável</dt><dd>{{ $formatarCpf($atleta->guardian_cpf) }}</dd></div>
                @endif
            </dl>
            <p class="conta__nota">Precisa corrigir algum deles? Fale com a organização do evento.</p>
        </section>

        <a class="botao botao--neutro" href="{{ route('senha.editar') }}">Alterar senha</a>
    </main>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tel = document.getElementById('phone');
        if (!tel) { return; }
        tel.addEventListener('input', function (e) {
            var v = e.target.value.replace(/\D/g, '').substring(0, 11);
            v = v.replace(/^(\d{2})(\d)/, '($1) $2');
            v = v.replace(/(\d)(\d{4})$/, '$1-$2');
            e.target.value = v;
        });
    });
</script>
@endpush
