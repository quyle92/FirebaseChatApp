<?php

namespace App\Jobs;

use Mockery;
use Throwable;
use App\Models\Player;
use LaravelFCM\Facades\FCM;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use LaravelFCM\Message\OptionsBuilder;
use Tests\Unit\DownstreamResponseTest;
use Illuminate\Queue\InteractsWithQueue;
use LaravelFCM\Message\PayloadDataBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use LaravelFCM\Mocks\MockDownstreamResponse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use LaravelFCM\Message\PayloadNotificationBuilder;
use LaravelFCM\Response\Exceptions\ServerResponseException;

class FCMWebpush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $player;
    protected $message;
    protected $fcm_tokens;
    protected $retried_count = 1;
    const DEFAULT_BACKOFF = 3;
    public $tries = 3;
    public $maxExceptions = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Player $player, string $message, array $fcm_tokens)
    {
        $this->player = $player;
        $this->message = $message;
        $this->fcm_tokens = $fcm_tokens; //fcm_tokens is app instance's registration token.
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DownstreamResponseTest::initMock();
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

        $downstreamResponse = FCM::sendTo($fcm_tokens, $option, $notification, $data);

        //remove invalid token from DB
        if ($invalid_tokens = array_merge($downstreamResponse->tokensToDelete(), $downstreamResponse->tokensWithError())) {
            Player::whereIn('fcm_token', array_keys($invalid_tokens))
                ->update(['fcm_token' => null]);
        }

        if ($tokens_to_modified = $downstreamResponse->tokensToModify()) {
            foreach($tokens_to_modified as $old_token => $new_token) {
                Player::where('fcm_token', $old_token)
                    ->update(['fcm_token' => $new_token]);
            }
        }

        //this happens when some fcm_tokens cannot be sent, but still return 2xx response code (https://firebase.google.com/docs/cloud-messaging/http-server-ref#error-codes, vendor/code-lts/laravel-fcm/src/Response/DownstreamResponse.php:::needToResend())
        if ($fcm_tokens_to_retried = $downstreamResponse->tokensToRetry()) {
            // implement exponential backoff.(http://snags88.github.io/backoff-strategy-for-laravel-jobs)
            $seconds_remaining = self::DEFAULT_BACKOFF * $this->attempts();

            self::cacheApiLimit($seconds_remaining);
            self::cacheFcmData($fcm_tokens_to_retried, $option, $notification, $data);

            return $this->release(
                 $seconds_remaining
            );
        }
    }

    //this happens when FCM bumps into 5xx error and throw exception as stated in  https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode (REMEMBER: failed() only triggers when there is exception thrown, even if 5xx error is returned but no exception thrown, failed() would not not get triggered).
    public function failed(Throwable $exception)
    {
        if($exception instanceof ServerResponseException && $this->retried_count <= $this->tries) {
            $seconds_remaining = $exception->retryAfter * $this->retried_count;
            self::cacheApiLimit($seconds_remaining);
            $this->retried_count += 1;

            return dispatch($this)->delay($seconds_remaining);
        }
    }

    protected static function getDataBuilder($input)
    {
        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData($input);
        return $dataBuilder->build();
    }

    protected static function cacheApiLimit($seconds_remaining)
    {
        Cache::put(
            'api_limit',
            now()->addSeconds($seconds_remaining)->timestamp,
            $seconds_remaining
        );
    }

    protected static function cacheFcmData($fcm_tokens_to_retried, $option, $notification, $data)
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
