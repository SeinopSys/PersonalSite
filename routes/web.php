<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ConnectionsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InlinerController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LRCController;
use App\Http\Controllers\NetSalaryController;
use App\Http\Controllers\SelfsignedController;
use App\Http\Controllers\ToolsController;
use App\Http\Controllers\TwoFactorAuthController;
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

// These require login
Route::group(['middleware' => ['auth']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats/availability', [DashboardController::class, 'statsAvailability']);
    Route::get('/dashboard/stats/uploads', [DashboardController::class, 'statsUploads']);
    Route::get('/availability', [DashboardController::class, 'availability'])->name('availability');
    Route::get('/account', [DashboardController::class, 'account'])->name('account');
    Route::post('/account/profile', [DashboardController::class, 'saveProfile']);
    Route::post('/dashboard/settings', [DashboardController::class, 'saveSettings']);
    Route::post('/dashboard/2fa/setup', [TwoFactorAuthController::class, 'setup']);
    Route::post('/dashboard/2fa/confirm', [TwoFactorAuthController::class, 'confirm']);
    Route::post('/dashboard/2fa/disable', [TwoFactorAuthController::class, 'disable']);
    Route::get('/dashboard/debug/events', [DashboardController::class, 'debugEvents']);
    Route::post('/dashboard/highlights', [DashboardController::class, 'storeHighlight']);
    Route::put('/dashboard/highlights/{tokenId}', [DashboardController::class, 'updateHighlight']);
    Route::post('/dashboard/highlights/{tokenId}/regenerate', [DashboardController::class, 'regenerateHighlight']);
    Route::post('/dashboard/highlights/{tokenId}/archive', [DashboardController::class, 'archiveHighlight']);
    Route::delete('/dashboard/highlights/{tokenId}', [DashboardController::class, 'destroyHighlight']);
    Route::post('/dashboard/highlights/{tokenId}/words', [DashboardController::class, 'storeHighlightWord']);
    Route::delete('/dashboard/highlights/{tokenId}/words/{wordId}', [DashboardController::class, 'destroyHighlightWord']);
    Route::get('/dashboard/highlights/export', [DashboardController::class, 'exportHighlights']);
    Route::post('/dashboard/highlights/import', [DashboardController::class, 'importHighlights']);
    Route::get('/connections', [ConnectionsController::class, 'index'])->name('connections');
    Route::post('/connections', [ConnectionsController::class, 'store']);
    Route::put('/connections/{id}', [ConnectionsController::class, 'update']);
    Route::post('/connections/{id}/archive', [ConnectionsController::class, 'archive']);
    Route::post('/connections/{id}/create-highlight', [ConnectionsController::class, 'createHighlightForConnection']);
    Route::delete('/connections/{id}', [ConnectionsController::class, 'destroy']);
    Route::get('/connections/{id}/events', [ConnectionsController::class, 'events']);
    Route::post('/connections/sources', [ConnectionsController::class, 'storeSource']);
    Route::put('/connections/sources/{id}', [ConnectionsController::class, 'updateSource']);
    Route::delete('/connections/sources/{id}', [ConnectionsController::class, 'destroySource']);
    Route::post('/connections/attributes', [ConnectionsController::class, 'storeAttributeDefinition']);
    Route::put('/connections/attributes/{id}', [ConnectionsController::class, 'updateAttributeDefinition']);
    Route::delete('/connections/attributes/{id}', [ConnectionsController::class, 'destroyAttributeDefinition']);
    Route::get('/connections/export', [ConnectionsController::class, 'exportConnections']);
    Route::post('/connections/import', [ConnectionsController::class, 'importConnections']);
    Route::post('/connections/import-connman', [ConnectionsController::class, 'importConnman']);
    Route::get('/connections/graph', [ConnectionsController::class, 'graph']);
    Route::post('/connections/graph-edges', [ConnectionsController::class, 'storeGraphEdge']);
    Route::delete('/connections/graph-edges/{id}', [ConnectionsController::class, 'destroyGraphEdge']);
    Route::post('/connections/auto-link-highlights', [ConnectionsController::class, 'autoLinkHighlightTokens']);
    Route::get('/uploads', [UploadsController::class, 'index']);
    Route::post('/uploads/regen', [UploadsController::class, 'regen']);
    Route::post('/uploads/setting/{action}', [UploadsController::class, 'setting']);
    Route::post('/uploads/wipe', [UploadsController::class, 'wipe']);
});
