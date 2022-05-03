<?php

namespace App\Jobs;

use Throwable;
use App\Models\Player;
use LaravelFCM\Facades\FCM;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use LaravelFCM\Message\OptionsBuilder;
use Illuminate\Queue\InteractsWithQueue;
use LaravelFCM\Message\PayloadDataBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use LaravelFCM\Message\PayloadNotificationBuilder;

class FCMWebpush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $player;
    public $message;
    public $fcm_tokens;
    public $tries = 5;
    public $currentRetryCount = 1;
    public $secondsRemaining;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($player, $message, $fcm_tokens)
    {
        $this->player = $player;
        $this->message = $message;
        $this->fcm_tokens = $fcm_tokens;//fcm_tokens is app instance's registration token.
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($timestamp = Cache::get('api_limit')) {
            return $this->release(
                $timestamp - time()
            );
        }

        $player_name =  $this->player->player_name;

        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60 * 20)
                ->setPriority('high');

        $notificationBuilder = new PayloadNotificationBuilder('New message from ' . $player_name);
        $notificationBuilder->setBody($this->message)
            ->setSound('default')
            ->setIcon('https://felgo.com/doc/images/used-in-examples/appdemos/qtws/assets/felgo-logo.png')
            ->setClickAction(config('app.url') . '/dashboard');

        $option = Cache::pull('option') ?? $optionBuilder->build();
        $notification = Cache::pull('notification') ?? $notificationBuilder->build();
        $data = Cache::pull('data') ?? self::getDataBuilder(['click_action' => config('app.url') . 'dashboard']);
        $fcm_tokens = Cache::pull('fcm_tokens_to_retried') ?? array_filter($this->fcm_tokens);

        $downstreamResponse = (array) FCM::sendTo($fcm_tokens, $option, $notification, $data);
        // info($downstreamResponse);

        //remove invalid token from DB
        if ($invalid_tokens = array_merge($downstreamResponse->tokensToDelete(), $downstreamResponse->tokensWithError())) {
            Player::whereIn('fcm_token', $invalid_tokens)
                ->delete();
        }

        if ($tokens_to_modified = $downstreamResponse->tokensToModify()) {
            foreach($tokens_to_modified as $old_token => $new_token) {
                Player::where('fcm_token', $old_token)
                    ->update(['fcm_token' => $new_token]);
            }
        }

        //this happens when some fcm_tokens cannot be sent (https://firebase.google.com/docs/cloud-messaging/http-server-ref#error-codes,
        //vendor/code-lts/laravel-fcm/src/Response/DownstreamResponse.php:::needToResend())
        if ($fcm_tokens_to_retried = $downstreamResponse->tokensToRetry()) {
            // implement exponential backoff.(http://snags88.github.io/backoff-strategy-for-laravel-jobs)
            $secondsRemaining = now()->addSeconds($this->currentRetryCount *  5);

            Cache::put(
                'api_limit',
                now()->addSeconds($secondsRemaining)->timestamp,
                $secondsRemaining
            );

            self::cacheData($fcm_tokens_to_retried, $option, $notification, $data);
            $this->currentRetryCount += 1;
            return $this->release(
                $secondsRemaining
            );
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        //this happens when all fcm_tokens cannot be sent (it can be UNAVAILABLE or INTERNAL error as specified in https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode).
        $this->secondsRemaining = $exception->retryAfter;
        $secondsRemaining = $this->secondsRemaining === 1 ? $this->secondsRemaining : $this->secondsRemaining + (30 * $this->currentRetryCount);
        Cache::put(
            'api_limit',
            now()->addSeconds($secondsRemaining)->timestamp,
            $secondsRemaining
        );

        $this->currentRetryCount++;

    }

    public function backoff()
    {
        $exponential_backoff = [];
        for ($i=1; $i <= $this->currentRetryCount; $i++) {
            $exponential_backoff[] = $this->secondsRemaining + (30 * $i);
        }

        return $exponential_backoff;
    }

    protected static function getDataBuilder($input)
    {
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData($input);
        return $dataBuilder->build();
    }

    protected static function cacheData($fcm_tokens_to_retried, $option, $notification, $data)
    {
        Cache::put(
            'fcm_tokens_to_retried',
            $fcm_tokens_to_retried,
        );

        Cache::put(
            'options',
            $option,
        );

        Cache::put(
            'notification',
            $notification,
        );

        Cache::put(
            'data',
            $data,
        );
    }
}
