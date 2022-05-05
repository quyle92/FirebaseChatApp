<?php

namespace Tests\Unit;

use Mockery;
// use PHPUnit\Framework\TestCase;
use Tests\TestCase;
use App\Models\Player;
use App\Jobs\FCMWebpush;

use Illuminate\Support\Str;
use LaravelFCM\Facades\FCM;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use LaravelFCM\Response\DownstreamResponse;
use LaravelFCM\Mocks\MockDownstreamResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LaravelFCM\Response\Exceptions\ServerResponseException;

class DownstreamResponseTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function test_handle_method_of_FCMWebPush()
    {
        $this->withoutExceptionHandling();

        self::initMock();
        $job = self::initFCMWebPush();
        $job->handle();

        $this->assertEquals(now()->addSeconds($job::DEFAULT_BACKOFF)->timestamp, cache('api_limit'));
        $this->assertEquals(['retry_token_1', 'retry_token_2'], cache('fcm_tokens_to_retried'));
        $this->assertNotNull(cache('options'));
        $this->assertNotNull(cache('notification'));
        $this->assertNotNull(cache('data'));
    }

    public function test_if_DownstreamResponse_throw_exception()
    {
        $this->withoutExceptionHandling();
        $this->expectException(\LaravelFCM\Response\Exceptions\ServerResponseException::class);

        $tokens = [
            'first_token',
            'second_token',
            'third_token',
        ];

        $response = new Response(503, ['Retry-After' => 30]);
        $logger = new \Monolog\Logger('test');
        $logger->pushHandler(new \Monolog\Handler\NullHandler());

        $downstreamResponse = new DownstreamResponse($response, $tokens, $logger);
    }

    public function test_failed_method_of_FCMWebPusher()
    {
        $retry_after = 30;
        $response = new Response(503, ['Retry-After' => $retry_after]);
        $ex = new ServerResponseException($response);

        $job = self::initFCMWebPush();
        $job->failed($ex);

        $this->assertEquals(now()->addSeconds($retry_after)->timestamp, cache('api_limit'));
    }

    public function test_invalid_tokens_can_be_deleted()
    {
        self::initMockInvalidTokens();
        $sender = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'sender',
        ]);
        $receiver_1 = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'test_name_1',
            'fcm_token' => 'error_token_1'
        ]);
        $receiver_2 = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'test_name_2',
            'fcm_token' => 'error_token_2'
        ]);

        FCMWebpush::dispatchSync($sender, $message = 'test message', $fcm_tokens = ['error_token_1', 'error_token_2']);
        $receiver_1->refresh();
        $receiver_2->refresh();
        $this->assertEquals(null, $receiver_1->fcm_token);
        $this->assertEquals(null, $receiver_2->fcm_token);
    }

    public function test_invalid_tokens_can_be_modified()
    {
        self::initMockModifiedTokens();
        $sender = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'sender',
        ]);
        $receiver_1 = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'test_name_1',
            'fcm_token' => 'old_token_1'
        ]);
        $receiver_2 = Player::create([
            'player_sn' => Str::orderedUuid()->toString(),
            'player_name' => 'test_name_2',
            'fcm_token' => 'old_token_2'
        ]);

        FCMWebpush::dispatchSync($sender, $message = 'test message', $fcm_tokens = ['modified_token_1', 'modified_token_2']);
        $receiver_1->refresh();
        $receiver_2->refresh();
        $this->assertSame('new_token_1', $receiver_1->fcm_token);
        $this->assertSame('new_token_2', $receiver_2->fcm_token);
    }

    public function test_job_can_be_pushed_to_queue()
    {
        Queue::fake();
        $player = Player::first();
        $message = "test message";
        $fcm_tokens = ['token_1', 'token_2'];
        FCMWebpush::dispatch($player, $message, $fcm_tokens);

        Queue::assertPushed(FCMWebpush::class);
    }

    //************* Static function *************/
    protected static function initFCMWebPush()
    {
        $player = Player::first();
        $message = "test message";
        $fcm_tokens = ['token_1', 'token_2'];
        return $job = new FCMWebpush($player, $message, $fcm_tokens);
    }

    //mock 200 response
    public static function initMock()
    {
        $numberSuccess = 2;
        $mockResponse = new MockDownstreamResponse($numberSuccess);

        $mockResponse->addTokenToRetry('retry_token_1');
        $mockResponse->addTokenToRetry('retry_token_2');
        $mockResponse->setMissingToken(true);

        $sender = Mockery::mock(\LaravelFCM\Sender\FCMSender::class);
        $sender->shouldReceive('sendTo')->once()->andReturn($mockResponse);
        app()->singleton('fcm.sender', function ($app) use ($sender) {
            return $sender;
        });
    }

    //mock 200 response
    public static function initMockInvalidTokens()
    {
        $numberSuccess = 0;
        $mockResponse = new MockDownstreamResponse($numberSuccess);

        $mockResponse->addTokenWithError('error_token_1', 'invalid_token');
        $mockResponse->addTokenWithError('error_token_2', 'invalid_token');

        $sender = Mockery::mock(\LaravelFCM\Sender\FCMSender::class);
        $sender->shouldReceive('sendTo')->once()->andReturn($mockResponse);
        app()->singleton('fcm.sender', function ($app) use ($sender) {
            return $sender;
        });
    }

    public static function initMockModifiedTokens()
    {
        $numberSuccess = 0;
        $mockResponse = new MockDownstreamResponse($numberSuccess);

        $mockResponse->addTokenToModify('old_token_1', 'new_token_1');
        $mockResponse->addTokenToModify('old_token_2', 'new_token_2');

        $sender = Mockery::mock(\LaravelFCM\Sender\FCMSender::class);
        $sender->shouldReceive('sendTo')->once()->andReturn($mockResponse);
        app()->singleton('fcm.sender', function ($app) use ($sender) {
            return $sender;
        });
    }

    //mock 5xx response
    public static function initMockExceptions()
    {
        $retry_after = 1;
        $mockResponse = new Response(503, ['Retry-After' => $retry_after]);
        $exception = new ServerResponseException($mockResponse);
        $sender = Mockery::mock(\LaravelFCM\Sender\FCMSender::class);
        $sender->shouldReceive('sendTo')->once()->andThrows($exception);
        app()->singleton('fcm.sender', function ($app) use ($sender) {
            return $sender;
        });
    }
}
//** Note */
//!(1) Mock example: https://stackoverflow.com/a/62179374/11297747, https://www.youtube.com/watch?v=h602bnWriyE, https://stefanzweifel.io/posts/create-mocks-for-api-clients-in-laravel (example with explanation).
//# dispatch() vs dispatchSync():
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