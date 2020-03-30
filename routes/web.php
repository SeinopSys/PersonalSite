<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of the routes that are handled
| by your application. Just tell Laravel the URIs it should respond
| to using a Closure or controller method. Build something great!
|
*/

Route::get('/', 'AboutController@index')->name('about');
Route::get('/about', 'AboutController@gotoIndex');
Route::get('/lang/{lang}', 'LanguageController@switchLang')->name('lang.switch');
Route::post('/upload', 'UploadsController@upload');
Route::get('/vlsm', function () {
    return redirect('/networking#vlsm', 301);
});
Route::get('/cidr', function () {
    return redirect('/networking#cidr', 301);
});
Route::get('/networking', 'ToolsController@networking')->name('networking');
Route::get('/imagecalc', 'ToolsController@imagecalc')->name('imagecalc');
Route::get('/self-signed', function () {
    return redirect('/selfsigned', 301);
});
Route::get('/selfsigned', 'SelfsignedController@index');
Route::get('/selfsigned/rootCA', 'SelfsignedController@rootCA');
Route::post('/selfsigned', 'SelfsignedController@make')->middleware(['recaptcha','throttle:25,5']);
Route::get('/lrc', 'LRCController@index')->name('lrc');
Route::get('/netsalary', 'NetSalaryController@index');
Route::get('/inliner', 'InlinerController@index');

Auth::routes(['register' => false, 'reset' => false]);
Route::get('register', 'Auth\RegisterController@showRegistrationForm')->name('register');
Route::post('register', 'Auth\RegisterController@register')->middleware('recaptcha');

// These require login
Route::group(['middleware' => ['auth']], function () {
    Route::get('/dashboard', 'DashboardController@index');
    Route::get('/uploads', 'UploadsController@index');
    Route::post('/uploads/regen', 'UploadsController@regen');
    Route::post('/uploads/setting/{action}', 'UploadsController@setting');
    Route::post('/uploads/wipe', 'UploadsController@wipe');
});
