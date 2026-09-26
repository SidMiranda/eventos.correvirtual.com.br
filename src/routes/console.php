<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Agendamentos
|--------------------------------------------------------------------------
| Precisa do cron da VPS chamando `schedule:run` a cada minuto (instalado
| pelo deploy — ver docs/runbook.md).
*/

// Token OAuth do organizador vence em 180 dias: renova com folga de 30
// (ADR 0008). Falha vira alerta imediato.
\Illuminate\Support\Facades\Schedule::command('mercadopago:renovar-tokens')
    ->dailyAt('04:10')
    ->withoutOverlapping();
