<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BankTransactionsController;
use App\Http\Controllers\BillsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InlinerController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LRCController;
use App\Http\Controllers\NetSalaryController;
use App\Http\Controllers\SelfsignedController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\ShareLinksController;
use App\Http\Controllers\ToolsController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\UploadFoldersController;
use App\Http\Controllers\UploadsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [AboutController::class, 'index'])->name('about');
Route::get('/about', [AboutController::class, 'gotoIndex']);
Route::get('/lang/{lang}', [LanguageController::class, 'switchLang'])->name('lang.switch');
Route::get('/vlsm', function () {
    return redirect('/networking#vlsm', 301);
});
Route::get('/cidr', function () {
    return redirect('/networking#cidr', 301);
});
Route::get('/networking', [ToolsController::class, 'networking'])->name('networking');
Route::get('/imagecalc', [ToolsController::class, 'imagecalc'])->name('imagecalc');
Route::get('/self-signed', function () {
    return redirect()->route('selfsigned', [], 301);
});
Route::get('/selfsigned', [SelfsignedController::class, 'index'])->name('selfsigned');
Route::get('/selfsigned/rootCA', [SelfsignedController::class, 'rootCA']);
Route::group(['middleware' => ['throttle:25,5']], function () {
    Route::post('/selfsigned', [SelfsignedController::class, 'make'])->name('selfsigned.make');
});
Route::get('/lrc', [LRCController::class, 'index'])->name('lrc');
Route::get('/netsalary', [NetSalaryController::class, 'index']);
Route::get('/inliner', [InlinerController::class, 'index']);

Auth::routes(['register' => false, 'reset' => false]);
Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::get('/login/2fa', [TwoFactorChallengeController::class, 'show'])->name('2fa.challenge');
Route::post('/login/2fa', [TwoFactorChallengeController::class, 'verify'])->name('2fa.verify');

// Public, read-only overview for whoever holds the secret link; throttled, and unknown links are all just a 404
Route::get('/share/{token?}', [ShareController::class, 'show'])
    ->where('token', '.*')
    ->middleware('throttle:60,1')
    ->name('share.show');

// These require login
Route::group(['middleware' => ['auth']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats/uploads', [DashboardController::class, 'statsUploads']);
    Route::get('/account', [DashboardController::class, 'account'])->name('account');
    Route::post('/account/profile', [DashboardController::class, 'saveProfile']);
    Route::post('/dashboard/2fa/setup', [TwoFactorAuthController::class, 'setup']);
    Route::post('/dashboard/2fa/confirm', [TwoFactorAuthController::class, 'confirm']);
    Route::post('/dashboard/2fa/disable', [TwoFactorAuthController::class, 'disable']);
    Route::get('/uploads', [UploadsController::class, 'index']);
    Route::post('/uploads/regen', [UploadsController::class, 'regen']);
    Route::post('/uploads/setting/{action}', [UploadsController::class, 'setting']);
    Route::post('/uploads/wipe', [UploadsController::class, 'wipe']);
    Route::get('/uploads/folders', [UploadFoldersController::class, 'tree']);
    Route::post('/uploads/folders', [UploadFoldersController::class, 'store']);
    Route::put('/uploads/folders/{id}', [UploadFoldersController::class, 'update']);
    Route::delete('/uploads/folders/{id}', [UploadFoldersController::class, 'destroy']);
    Route::post('/uploads/folders/{id}/regen', [UploadFoldersController::class, 'regenKey']);
});

// Bills and bank transactions are personal data; every user only ever sees their own
Route::group(['middleware' => ['auth', 'user']], function () {
    Route::get('/bills', [BillsController::class, 'index']);
    Route::get('/bills/data', [BillsController::class, 'data']);
    Route::get('/bills/shares', [ShareLinksController::class, 'index']);
    Route::post('/bills/shares', [ShareLinksController::class, 'store']);
    Route::delete('/bills/shares/{id}', [ShareLinksController::class, 'destroy']);
    Route::post('/bills', [BillsController::class, 'store']);
    Route::put('/bills/{id}', [BillsController::class, 'update']);
    Route::delete('/bills/{id}', [BillsController::class, 'destroy']);
    Route::post('/bank-transactions', [BankTransactionsController::class, 'store']);
    Route::post('/bank-transactions/group', [BankTransactionsController::class, 'group']);
    Route::post('/bank-transactions/ungroup', [BankTransactionsController::class, 'ungroup']);
    Route::put('/bank-transactions/{id}', [BankTransactionsController::class, 'update']);
    Route::delete('/bank-transactions/{id}', [BankTransactionsController::class, 'destroy']);
});
