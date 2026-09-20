<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Subscriptions\SubscribeController;
use App\Http\Controllers\Events\EventsController;
use App\Http\Controllers\Subscriptions\PixController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventModalityController as AdminModalityController;
use App\Http\Controllers\Admin\EventKitController as AdminKitController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\PhotoController as AdminPhotoController;
use App\Http\Controllers\Admin\SponsorController as AdminSponsorController;
use App\Http\Controllers\Admin\TeamController as AdminTeamController;
use App\Http\Controllers\Admin\CatalogoController as AdminCatalogoController;

use App\Services\MercadoPagoService;

/*
|--------------------------------------------------------------------------
| Index
|--------------------------------------------------------------------------
*/

Route::get('/', [EventsController::class, 'index'])->name('home');

// Route::get('/event/{id}', [EventsController::class, 'show'])->name('events.show');

Route::get('/event/{event_id}', [EventsController::class, 'show'])->name('event.show');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::get('/register', [RegisterController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [RegisterController::class, 'register']);

Route::get('/logout', [LoginController::class, 'logout']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Verificação Email
|--------------------------------------------------------------------------
*/

Route::get('/verify-email', [VerifyEmailController::class, 'showVerifyInputCode'])->name('verify-email.show');
Route::post('/verify-email', [VerifyEmailController::class, 'verifyEmail']);

/*
|--------------------------------------------------------------------------
| PIX
|--------------------------------------------------------------------------
*/

Route::get('/teste-pix', function () {

    $pix = MercadoPagoService::createPixPayment(
        1.00,
        'sidney.miranda2013@gmail.com'
    );

    return view('teste-pix', compact('pix'));

});

// `auth` explícito: o controller usa auth()->id() para só achar a inscrição de
// quem está logado. Sem o middleware, um visitante caía num 404 seco em vez de
// ser mandado para o login.
Route::post('/event-pay', [PixController::class, 'generatePix'])
    ->middleware('auth')
    ->name('event-pay');

/*
|--------------------------------------------------------------------------
| Inscrição
|--------------------------------------------------------------------------
*/

Route::get('/my-subscriptions', [SubscribeController::class, 'mySubscriptions'])
    ->middleware('auth')
    ->name('subscriptions.my');

Route::get('/subscribe/event/{event_id}', [SubscribeController::class, 'showSubscribeForm'])
    ->middleware('auth')
    ->name('subscribe');

Route::post('/subscribe/event/{event_id}', [SubscribeController::class, 'subscribe']);

// Prévia do cupom no formulário: valida e devolve os valores em JSON, sem
// criar nada. O throttle é a defesa barata contra tentar códigos no chute.
Route::post('/subscribe/event/{event_id}/cupom', [SubscribeController::class, 'previaDoCupom'])
    ->middleware(['auth', 'throttle:20,1'])
    ->name('subscribe.cupom');

Route::post('/subscription/cancel', [SubscribeController::class, 'cancel'])
    ->middleware('auth')
    ->name('subscriptions.cancel');

Route::get('/subscriptions/{id}/success', [PixController::class, 'success'])->name('subscriptions.success');

/*
|--------------------------------------------------------------------------
| Painel administrativo do organizador
|--------------------------------------------------------------------------
| Duas travas: precisa estar logado E ser organizer_admin com organizador
| preenchido. Dentro do painel o escopo vem do usuário logado, não do domínio
| (ver docs/specs/painel-admin.md).
*/

Route::middleware(['auth', 'organizer.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('eventos', AdminEventController::class)
            ->except(['show'])
            ->parameters(['eventos' => 'id']);

        // Modalidades e kits são aninhados de propósito: não existem fora de um
        // evento, e a rota aninhada torna impossível cadastrar um kit sem dizer
        // de qual evento ele é.
        Route::resource('eventos.modalidades', AdminModalityController::class)
            ->except(['show'])
            ->parameters(['eventos' => 'evento', 'modalidades' => 'id']);

        Route::resource('eventos.kits', AdminKitController::class)
            ->except(['show'])
            ->parameters(['eventos' => 'evento', 'kits' => 'id']);

        // Atalhos do menu lateral: listam modalidades e kits de todos os eventos
        // do organizador, e o botão de cadastrar pergunta em qual evento antes
        // de cair no formulário aninhado.
        Route::get('modalidades', [AdminCatalogoController::class, 'modalidades'])->name('modalidades.geral');
        Route::get('kits', [AdminCatalogoController::class, 'kits'])->name('kits.geral');
        Route::get('catalogo/{tipo}/novo', [AdminCatalogoController::class, 'novo'])
            ->whereIn('tipo', ['modalidades', 'kits'])
            ->name('catalogo.novo');

        // Cupons: uma tela só, com o formulário num modal da própria listagem —
        // por isso sem `create` e sem `edit`. O cupom pertence a um evento, mas
        // a rota não é aninhada: quem chega aqui quer ver os descontos que estão
        // de pé agora, não navegar evento por evento.
        Route::resource('cupons', AdminCouponController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['cupons' => 'id']);

        Route::patch('cupons/{id}/status', [AdminCouponController::class, 'status'])
            ->name('cupons.status');

        // Equipes pertencem ao organizador, não ao evento.
        Route::resource('equipes', AdminTeamController::class)
            ->except(['show'])
            ->parameters(['equipes' => 'id']);

        // Patrocinadores também: o mesmo apoiador cobre várias provas no ano.
        Route::resource('patrocinadores', AdminSponsorController::class)
            ->except(['show'])
            ->parameters(['patrocinadores' => 'id']);

        // A galeria de fotos da home: do organizador, cadastro em lote.
        Route::resource('fotos', AdminPhotoController::class)
            ->except(['show'])
            ->parameters(['fotos' => 'id']);

    });
