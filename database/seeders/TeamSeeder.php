<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Player;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('teams')->truncate();
        $team_sn = Str::orderedUuid()->toString();
        for ($i = 1; $i <= 1; $i++) {
            $teams[] = [
                "team_sn" => $team_sn,
            ];
        }
        Team::insert($teams);

    }


}

