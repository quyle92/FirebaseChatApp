<?php

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

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', 'HomeController@index')->name('dashboard');
    Route::post('/dashboard', 'HomeController@createChat')->name('dashboard.createChat');
    Route::post('api/start-writting', 'HomeController@startWritting');
    Route::post('api/stop-writting', 'HomeController@stopWritting');
    Route::get('api/messages', 'HomeController@getMessages');
});

require __DIR__.'/auth.php';
