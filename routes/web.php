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
    data_delete_collection($projectId = env('ProjectID'), $collectionName = 'teams', $batchSize = 10);

    $teams = Team::with('players')->get();
    foreach ($teams as $team) {
        $db->collection('teams')->document($team->team_sn)->delete();
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
    Route::post('api/send-push-message', 'HomeController@sendPushMessage')->name('dashboard.createChat');
    Route::post('api/start-writting', 'HomeController@startWritting');
    Route::post('api/stop-writting', 'HomeController@stopWritting');
    Route::get('api/messages', 'HomeController@getMessages');
});

require __DIR__.'/auth.php';
function data_delete_collection(string $projectId, string $collectionName, int $batchSize)
{
    // Create the Cloud Firestore client
    $db = new FirestoreClient([
        'projectId' => $projectId,
    ]);
    $collectionReference = $db->collection($collectionName);
    $documents = $collectionReference->limit($batchSize)->documents();
    while (!$documents->isEmpty()) {
        foreach ($documents as $document) {
            printf('Deleting document %s' . PHP_EOL, $document->id());
            $document->reference()->delete();
        }
        $documents = $collectionReference->limit($batchSize)->documents();
    }
}