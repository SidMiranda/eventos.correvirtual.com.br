<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Conta\InscricoesDoAtletaController;
use App\Http\Controllers\Conta\PerfilDoAtletaController;
use App\Http\Controllers\Subscriptions\SubscribeController;
use App\Http\Controllers\Events\EventsController;
use App\Http\Controllers\Subscriptions\PixController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventModalityController as AdminModalityController;
use App\Http\Controllers\Admin\EventKitController as AdminKitController;
use App\Http\Controllers\Admin\AthleteController as AdminAthleteController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\AboutController as AdminAboutController;
use App\Http\Controllers\Admin\AgeCategoryController as AdminAgeCategoryController;
use App\Http\Controllers\Admin\EventLotController as AdminLotController;
use App\Http\Controllers\Admin\EventPriceController as AdminPriceController;
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

// A busca do campo "Cidade" do cadastro. Pública porque o cadastro é de quem
// ainda não tem conta; o throttle existe para ninguém usar isto como serviço
// de consulta às nossas custas.
Route::get('/cidades', [CityController::class, 'buscar'])
    ->middleware('throttle:60,1')
    ->name('cidades.buscar');

/*
|--------------------------------------------------------------------------
| Senha
|--------------------------------------------------------------------------
| Troca de senha de quem já está logado. O item "Alterar senha" do menu
| apontava para `#!` desde sempre — não existia tela. Recuperação por e-mail
| ("esqueci minha senha") continua não existindo; ver docs/backlog.md.
*/

Route::middleware('auth')->group(function () {
    Route::get('/alterar-senha', [PasswordController::class, 'edit'])->name('senha.editar');
    Route::put('/alterar-senha', [PasswordController::class, 'update'])->name('senha.atualizar');
});

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

// "Minha conta" (docs/specs/area-do-atleta.md). O endereço antigo continua
// servindo a mesma tela, sem redirecionar: os e-mails já enviados apontam
// para ele, e o aviso de "inscrição feita" vive no flash da sessão — um
// redirecionamento a mais o consumiria antes de a tela abrir.
Route::middleware('auth')->group(function () {
    Route::get('/minha-conta', [InscricoesDoAtletaController::class, 'index'])->name('conta.inscricoes');
    Route::get('/my-subscriptions', [InscricoesDoAtletaController::class, 'index'])->name('subscriptions.my');
    Route::get('/minha-conta/perfil', [PerfilDoAtletaController::class, 'edit'])->name('conta.perfil');
    Route::put('/minha-conta/perfil', [PerfilDoAtletaController::class, 'update']);
    Route::get('/minha-conta/inscricoes/{id}', [InscricoesDoAtletaController::class, 'edit'])->whereNumber('id')->name('conta.inscricao');
    Route::put('/minha-conta/inscricoes/{id}', [InscricoesDoAtletaController::class, 'update'])->whereNumber('id');
});

Route::get('/subscribe/event/{event_id}', [SubscribeController::class, 'showSubscribeForm'])
    ->middleware('auth')
    ->name('subscribe');

Route::post('/subscribe/event/{event_id}', [SubscribeController::class, 'subscribe']);

// Prévia do cupom no formulário: valida e devolve os valores em JSON, sem
// criar nada. O throttle é a defesa barata contra tentar códigos no chute.
Route::post('/subscribe/event/{event_id}/cotacao', [SubscribeController::class, 'cotacao'])
    ->middleware(['auth', 'throttle:20,1'])
    ->name('subscribe.cotacao');

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

        // Lotes e categorias etárias também são do evento (ADR 0007). A grade
        // de preços é uma tela só por evento: GET mostra, PUT salva tudo.
        Route::resource('eventos.lotes', AdminLotController::class)
            ->except(['show'])
            ->parameters(['eventos' => 'evento', 'lotes' => 'id']);

        Route::resource('eventos.categorias', AdminAgeCategoryController::class)
            ->except(['show'])
            ->parameters(['eventos' => 'evento', 'categorias' => 'id']);

        Route::get('eventos/{evento}/precos', [AdminPriceController::class, 'edit'])->name('eventos.precos.edit');
        Route::put('eventos/{evento}/precos', [AdminPriceController::class, 'update'])->name('eventos.precos.update');

        // Atalhos do menu lateral: listam modalidades e kits de todos os eventos
        // do organizador, e o botão de cadastrar pergunta em qual evento antes
        // de cair no formulário aninhado.
        Route::get('modalidades', [AdminCatalogoController::class, 'modalidades'])->name('modalidades.geral');
        Route::get('kits', [AdminCatalogoController::class, 'kits'])->name('kits.geral');
        Route::get('lotes', [AdminCatalogoController::class, 'lotes'])->name('lotes.geral');
        Route::get('categorias', [AdminCatalogoController::class, 'categorias'])->name('categorias.geral');
        Route::get('catalogo/{tipo}/novo', [AdminCatalogoController::class, 'novo'])
            ->whereIn('tipo', ['modalidades', 'kits', 'lotes', 'categorias'])
            ->name('catalogo.novo');

        // Inscrições: a lista com filtros e o relatório em PDF. O PDF vem
        // ANTES do resource para /inscricoes/pdf não ser lido como um id.
        Route::get('inscricoes/pdf', [AdminSubscriptionController::class, 'pdf'])->name('inscricoes.pdf');
        Route::get('inscricoes', [AdminSubscriptionController::class, 'index'])->name('inscricoes.index');

        // Atletas: só consulta. "Atleta do organizador" é quem tem ao menos
        // uma inscrição num evento dele — a conta em si é da plataforma.
        Route::get('atletas', [AdminAthleteController::class, 'index'])->name('atletas.index');
        Route::get('atletas/{id}', [AdminAthleteController::class, 'show'])->name('atletas.show');

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

        // O bloco "Sobre nós" da home. Registro único — é uma seção do site,
        // não uma lista —, por isso só editar e salvar, sem index nem destroy.
        Route::get('sobre', [AdminAboutController::class, 'edit'])->name('sobre.edit');
        Route::put('sobre', [AdminAboutController::class, 'update'])->name('sobre.update');

    });
