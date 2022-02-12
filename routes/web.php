<?php

use App\Models\Team;
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

Route::get('/add-teams-doc', function () {
    $db = new FirestoreClient([
        'projectId' => env('ProjectID'),
    ]);

    $teams = Team::with('players')->get();
    foreach ($teams as $team) {
        $player_sn_list = $team->players->map(function ($player) {
            return $player->player_sn;
        });
        $docRef = $db->collection('teams')->document($team->team_sn);
        $docRef->set([
            'player_sn_list' => $player_sn_list->toArray()
        ], ['merge' => true]);
    }
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', 'HomeController@index')->name('dashboard');
    Route::post('/dashboard', 'HomeController@createChat')->name('dashboard.createChat');
    Route::post('api/start-writting', 'HomeController@startWritting');
    Route::post('api/stop-writting', 'HomeController@stopWritting');
    Route::get('api/messages', 'HomeController@getMessages');
});

require __DIR__.'/auth.php';
