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
Route::post('/upload', [UploadsController::class, 'upload'])->middleware('throttle:300,5');
Route::post('/upload/{key}', [UploadsController::class, 'uploadByKey'])->middleware('throttle:300,5')->name('uploads.uploadByKey');
Route::delete('/upload/{deleteKey}', [UploadsController::class, 'deleteByKey'])->middleware('throttle:300,5')->name('uploads.delete');

