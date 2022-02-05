<?php

namespace App\Http\Controllers;

use FCM;
use App\Models\Chat;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;

class HomeController extends Controller
{
    public function index()
    {
        $team_sn = Auth::user()->team_sn;
        $chats = Chat::where('team_sn', $team_sn)->get();
        $this->removeChatMessage($team_sn);
        return view('dashboard', compact('chats'));
    }

    public function createChat(Request $request)
    {
        $player = Auth::user();
        $message = $request->message;
        $chat = new Chat([
            'team_sn' => $player->team_sn,
            'player_sn' => $player->player_sn,
            'player_name' => $player->player_name,
            'message' => $message
        ]);

        $chat->save();

        $this->broadcastMessage($player, $message);
        return redirect()->back();
    }

    protected function broadcastMessage($player, $message)
    {
        $team_sn = $player->team_sn;
        $player_name = $player->player_name;
        $fcm_token = $player->fcm_token;

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20);

        $notificationBuilder = new PayloadNotificationBuilder('New message from ' . $player_name);
        $notificationBuilder->setBody($message)
                            ->setSound('default')
                            ->setClickAction(config('app.url') . 'dashboard');

        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();

        $token = Player::where('team_sn', $team_sn)
                        ->get('fcm_token')
                        ->pluck('fcm_token')
                        ->except([$fcm_token])
                        ->toArray();

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(['a_data' => 'my_data']);
        $data = $dataBuilder->build();

        $downstreamResponse = FCM::sendTo(array_filter($token), $option, $notification, $data);

        return $downstreamResponse->numberSuccess();
    }

    protected function removeChatMessage($team_sn)
    {
        if(Chat::where('team_sn', $team_sn)->get()->count() > 5)
            Chat::where('team_sn', $team_sn)->delete();
    }
}
