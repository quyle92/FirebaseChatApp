<?php

use App\Models\Team;
use App\Models\Player;
use App\Jobs\FCMWebpush;
use Illuminate\Support\Facades\Route;
use Google\Cloud\Firestore\FirestoreClient;
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



Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', 'HomeController@index')->name('dashboard');
    Route::post('api/send-push-message', 'HomeController@sendPushMessage')->name('dashboard.createChat');
    Route::post('api/start-writting', 'HomeController@startWritting');
    Route::post('api/stop-writting', 'HomeController@stopWritting');
    Route::get('api/messages', 'HomeController@getMessages');
});
Route::get('/test', function () {
    $player = Player::first();
    $message = "test message";
    $fcm_tokens = ['token_1', 'token_2', 'token_3'];
    FCMWebpush::dispatch($player, $message, $fcm_tokens);
});
require __DIR__.'/auth.php';
