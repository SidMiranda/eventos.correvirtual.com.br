@extends('layouts.auth')

@section('title','Registrar')

@section('content')

<style>
    /* O resto do formulário é o forms.css (a cidade traz o próprio estilo,
       no componente campo-cidade).
       Inline porque esta tela ainda está no CSS antigo, pendente do redesign
       (backlog) — não vale abrir arquivo novo para isso. */
    .aviso-responsavel { font-size: 13px; color: #666; margin: -8px 0 12px; text-align: left; }
    .campo-pcd { display: flex; align-items: center; gap: 8px; width: 100%; margin: 0 0 12px; font-size: 15px; color: #333; text-align: left; cursor: pointer; }
    .campo-pcd input { width: 18px; height: 18px; margin: 0; flex: none; }
</style>

<div class="modal-overlay" style="display:flex">
<div class="modal">

<form method="POST" action="{{ route('register') }}" class="form-container">

@csrf

<h2>Registrar</h2>

<input 
type="text"
name="name"
placeholder="Nome completo"
value="{{ old('name') }}"
required
>

<input 
type="date"
name="birth_date"
value="{{ old('birth_date') }}"
required
>

<select name="sex" required>

<option value="">Sexo</option>

<option value="male">
Masculino
</option>

<option value="female">
Feminino
</option>

<option value="other">
Outro
</option>

</select>

<x-campo-cidade :texto="old('cidade')" :cidade-id="old('city_id')" />

<input 
type="text"
name="phone"
placeholder="Celular"
value="{{ old('phone') }}"
required
>

<input 
type="email"
name="email"
placeholder="Email"
value="{{ old('email') }}"
required
>

<input 
type="text"
name="cpf"
placeholder="CPF"
value="{{ old('cpf') }}"
required
>

{{-- Só aparece para quem informa menos de 18 anos. Nasce escondido e o
     JavaScript abaixo mostra conforme a data de nascimento — mas quem manda
     é o servidor: esconder no front não é validar (ver RegisterController). --}}
<div id="blocoResponsavel" hidden>
    <input
    type="text"
    name="guardian_cpf"
    id="campoCpfResponsavel"
    placeholder="CPF do responsável"
    value="{{ old('guardian_cpf') }}"
    >
    <p class="aviso-responsavel">Quem tem menos de 18 anos precisa do CPF de um responsável.</p>
</div>

<label class="campo-pcd">
    <input type="checkbox" name="is_pcd" value="1" @checked(old('is_pcd'))>
    Sou PCD (pessoa com deficiência)
</label>

<input 
type="password"
name="password"
placeholder="Senha"
required
>

<button type="submit" class="btn-primary">
Registrar
</button>

<div class="form-links">

<a href="{{ route('login') }}">
Já tenho conta
</a>

</div>

</form>

</div>
</div>

@endsection

@push('scripts')
<script>
    const cpfInput = document.querySelector('input[name="cpf"]');
    if(cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ""); // Remove tudo que não for número
            if (value.length > 11) value = value.substring(0, 11); // Limita a 11 dígitos
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d)/, "$1.$2");
            value = value.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
            e.target.value = value;
        });
    }

    /* ------------------------------------------------------------------
       CPF do responsável: aparece só para quem informa menos de 18 anos.

       Esconder e mostrar aqui é conveniência. Quem decide é o servidor — a
       data de nascimento e o CPF do responsável são conferidos lá, porque
       qualquer um remove um `hidden` no navegador.
       ------------------------------------------------------------------ */
    (function () {
        var nascimento = document.querySelector('input[name="birth_date"]');
        var bloco = document.getElementById('blocoResponsavel');
        var campo = document.getElementById('campoCpfResponsavel');
        if (!nascimento || !bloco || !campo) { return; }

        function menorDeIdade(valor) {
            if (!valor) { return false; }
            var data = new Date(valor + 'T00:00:00');
            if (isNaN(data)) { return false; }

            var hoje = new Date();
            var idade = hoje.getFullYear() - data.getFullYear();
            var mes = hoje.getMonth() - data.getMonth();
            if (mes < 0 || (mes === 0 && hoje.getDate() < data.getDate())) { idade--; }

            return idade < 18;
        }

        function conferir() {
            var precisa = menorDeIdade(nascimento.value);
            bloco.hidden = !precisa;
            campo.required = precisa;
            /* Virou maior de idade no meio do preenchimento: o que estava
               digitado no campo escondido não pode ir junto. */
            if (!precisa) { campo.value = ''; }
        }

        nascimento.addEventListener('change', conferir);
        nascimento.addEventListener('input', conferir);

        /* Voltou do servidor com erro: a data preenchida manda de novo. */
        conferir();

        campo.addEventListener('input', function (e) {
            var v = e.target.value.replace(/\D/g, "").substring(0, 11);
            v = v.replace(/(\d{3})(\d)/, "$1.$2");
            v = v.replace(/(\d{3})(\d)/, "$1.$2");
            v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
            e.target.value = v;
        });
    })();

    const phoneInput = document.querySelector('input[name="phone"]');
    if(phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ""); // Remove tudo que não for número
            if (value.length > 11) value = value.substring(0, 11); // Limita a 11 dígitos (DDD + 9 dígitos)
            value = value.replace(/^(\d{2})(\d)/g, "($1) $2"); // Coloca parênteses no DDD
            value = value.replace(/(\d)(\d{4})$/, "$1-$2"); // Coloca o hífen antes dos últimos 4 dígitos
            e.target.value = value;
        });
    }
</script>
@endpush