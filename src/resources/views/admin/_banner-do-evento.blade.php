{{-- O cabeçalho das telas de um evento mostra o banner DAQUELE evento
     (2026-09-25): com o degradê igual em tudo, o organizador não sabia em
     que evento estava. O layout lê esta seção; evento sem banner fica no
     degradê de sempre. Seção e não variável: uma variável $event solta de
     um @foreach vazaria para o layout e pintaria a tela errada. --}}
@if ($event->banner_url)
    @section('banner_do_cabecalho', \App\Support\Arquivos::bannerDoEvento($event))
@endif
