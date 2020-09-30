<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InlinerController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LRCController;
use App\Http\Controllers\NetSalaryController;
use App\Http\Controllers\SelfsignedController;
use App\Http\Controllers\ToolsController;
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
Route::post('/upload', [UploadsController::class, 'upload']);
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

// These require login
Route::group(['middleware' => ['auth']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/uploads', [UploadsController::class, 'index']);
    Route::post('/uploads/regen', [UploadsController::class, 'regen']);
    Route::post('/uploads/setting/{action}', [UploadsController::class, 'setting']);
    Route::post('/uploads/wipe', [UploadsController::class, 'wipe']);
});
