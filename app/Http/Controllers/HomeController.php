<?php

namespace App\Http\Controllers;

use FCM;
use App\Models\Chat;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Player;
use App\Jobs\FCMWebpush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LaravelFCM\Message\OptionsBuilder;
use Illuminate\Support\Facades\Storage;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $player = Auth::user();
        $team_sn = $player->team_sn;
        $google_application_credentials = json_decode((file_get_contents(env('GOOGLE_APPLICATION_CREDENTIALS'))));
        $private_key  = $google_application_credentials->private_key;
        $client_email = $google_application_credentials->client_email;
        $uid = $player->player_sn;

        if(!$jwt = $player->jwt ) {
            $jwt = $this->createNewJWT($client_email, $uid, $player, $private_key);
        }

        //Option 1 to get jwt payload:
        list($header, $payload, $signature) = explode(".", $jwt);
        $payload = json_decode(base64_decode($payload));

        //Option 2 to get jwt payload:
        // $public_key = $this->createPublicKey($private_key);
        // $payload = JWT::decode($jwt, $public_key, ['RS256']);

        $expire_time = $payload->exp;
        if (now()->timestamp > $expire_time) {
            $jwt = $this->createNewJWT($client_email, $uid, $player, $private_key);
        }

        return view('dashboard', compact('jwt'));
    }

    protected function createPublicKey($private_key)
    {
        $open_ssl_asymmetric_key = openssl_pkey_get_private($private_key);
        $public_key = openssl_pkey_get_details($open_ssl_asymmetric_key)['key'];//(1)
        return $public_key;
    }

    protected function createNewJWT($client_email, $uid, $player, $private_key)
    {
        $payload = array(
            "iss" => $client_email,
            "sub" => $client_email,
            "aud" => "https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit",
            "iat" => now()->timestamp,
            "exp" => now()->timestamp + (60 * 60),  // Maximum expiration time is one hour
            "uid" => $uid,
        );
        $jwt = JWT::encode($payload, $private_key, 'RS256');
        $player->jwt = $jwt;
        $player->save();

        return $jwt;
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

    public function sendPushMessage(Request $request)
    {
        $player = Auth::user();
        $message = $request->message;

        // $downstreamResponse = (array) $this->broadcastMessage($player, $message);
        // info(json_encode( $downstreamResponse));
        // return response()->json($downstreamResponse, 200);

        $fcm_tokens = $this->getFcmToken($player->fcm_token);
        FCMWebpush::dispatch($player, $message, $fcm_tokens);//(2)
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

        $data = $this->getDataBuilder(['click_action' => config('app.url') . 'dashboard']);

        return $downstreamResponse = FCM::sendTo(array_filter($token), $option, $notification, $data);

        // return $downstreamResponse->numberSuccess();
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

/**
 * Notes
 */
//(1): create public key from private key https://github.com/firebase/php-jwt/issues/116#issuecomment-260809197
//#(2) dispatch() vs dispatchSync():
//* dispatch() will push job to queue and process on another process/thread.
//* dispatchSync() (with ShouldQueue interface):
//- NOT push job to queue and execute immediately on same process/thread.
//- DO run through the same pipeline as any other queued job (i.e will run through middleware(), failed(), etc...).
//- the return of the handle method will NOT be available to the request.
//* dispatchSync() (without ShouldQueue interface):
//- NOT push job to queue and execute immediately on same process/thread,
//- NOT run through the same pipeline as any other queued job (i.e NOT will run through middleware(), failed(), etc...).
//- the return of the handle method will be available to the request.
//Ref: https://laracasts.com/discuss/channels/laravel/dispatchnow-vs-dispatchsync?page=1&replyId=645601