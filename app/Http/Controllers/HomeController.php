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
        $chats = Chat::where('team_sn', $team_sn)
                    ->get()
                    ->map(function($item) {
                        if($item->player_sn === Auth::user()->player_sn ){
                            $item->is_owner = 1;
                        }
                        else {
                            $item->is_owner = 0;
                        }
                        return $item;
                    });

        $this->removeChatMessage($team_sn);
        return view('dashboard', compact('chats'));
    }

    public function getMessages()
    {
        $team_sn = Auth::user()->team_sn;
        $chats = Chat::where('team_sn', $team_sn)
                    ->get(['player_sn', 'player_name', 'message'])
                    ->map(function ($item) {
                        if ($item->player_sn === Auth::user()->player_sn) {
                            $item->is_owner = 1;
                        } else {
                            $item->is_owner = 0;
                        }
                        return $item;
                    });
        return response()->json($chats);
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
        $player_name = $player->player_name;
        $fcm_token = $player->fcm_token;

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20)
        ->setPriority('high');

        $notificationBuilder = new PayloadNotificationBuilder('New message from ' . $player_name);
        $notificationBuilder->setBody($message)
                            ->setSound('default')
                            ->setIcon('https://felgo.com/doc/images/used-in-examples/appdemos/qtws/assets/felgo-logo.png')
                            ->setClickAction(config('app.url') . '/dashboard')
                            ;

        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();

        $token = $this->getFcmToken($fcm_token);

        $data = $this->getDataBuilder(['click_action' => config('app.url') . '/dashboard']);

        $downstreamResponse = FCM::sendTo(array_filter($token), $option, $notification, $data);

        return $downstreamResponse->numberSuccess();
    }

    protected function removeChatMessage($team_sn)
    {
        if(Chat::where('team_sn', $team_sn)->get()->count() >2)
            Chat::where('team_sn', $team_sn)->delete();
    }

    public function startWritting(Request $request)
    {
        $fcm_token = $request->fcmToken;
        $token = $this->getFcmToken($fcm_token);

        $data = $this->getDataBuilder(['player' => Auth::user(), 'action' => 'write']);

        $downstreamResponse = FCM::sendTo(array_filter($token), $option = null, $notification = null, $data );

        return $downstreamResponse->numberSuccess();
    }

    public function stopWritting(Request $request)
    {
        $fcm_token = $request->fcmToken;
        $token = $this->getFcmToken($fcm_token);

        $data = $this->getDataBuilder(['player' => Auth::user(), 'action' => 'stop']);

        $downstreamResponse = FCM::sendTo(array_filter($token), $option = null, $notification = null, $data);

        return $downstreamResponse->numberSuccess();
    }

    protected function getFcmToken($fcm_token)
    {
        $team_sn = Auth::user()->team_sn;
        return Player::where('team_sn', $team_sn)
            ->where('fcm_token', '<>', $fcm_token)
            ->get('fcm_token')
            ->pluck('fcm_token')
            ->except([$fcm_token])
            ->toArray();
    }

    protected function getDataBuilder($input)
    {
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData($input);
        return $dataBuilder->build();
    }
}
