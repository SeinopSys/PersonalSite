<?php

use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\UploadsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/availability/{name}', [AvailabilityController::class, 'show']);
Route::post('/upload', [UploadsController::class, 'upload']);
Route::post('/upload/{key}', [UploadsController::class, 'uploadByKey'])->name('uploads.uploadByKey');
Route::delete('/upload/{deleteKey}', [UploadsController::class, 'deleteByKey'])->name('uploads.delete');

