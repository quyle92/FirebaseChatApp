<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;

class FCMController extends Controller
{
    public function index(Request $request)
    {
        $fcm_token = $request->fcm_token;
        $player_sn = $request->player_sn;

        $player = Player::findOrFail($player_sn);
        $player->fcm_token = $fcm_token;
        $player->save();

        return response()->json(['success' => true, 'message' => 'token inserted successfully.']);
    }


}
