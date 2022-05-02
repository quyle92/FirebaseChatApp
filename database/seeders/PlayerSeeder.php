<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Str;
use Faker\Generator as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Google\Cloud\Firestore\FirestoreClient;

class PlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(Faker $faker)
    {
        DB::table('players')->truncate();
        $team_sn = Team::first()->team_sn;
        for ($i = 1; $i <= 5; $i++) {
            $players[] = [
                "player_sn" => Str::orderedUuid()->toString(),
                "player_name" => "user" . $i,
                "facebook_id" => sha1($i),
                "team_sn" => $team_sn,
            ];
        }
        Player::insert($players);

        $this->createFirebaseTeamCollection();
    }

    public function createFirebaseTeamCollection()
    {
        $db = new FirestoreClient([
            'projectId' => env('ProjectID'),
        ]);
        $this->data_delete_collection($projectId = env('ProjectID'), $collectionName = 'teams', $batchSize = 10);

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
    }

    public function data_delete_collection(string $projectId, string $collectionName, int $batchSize)
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
}
