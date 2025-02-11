<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AndroidTvCode;
use App\Models\User;
use App\Services\TokenService;
use App\Services\TvCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Class TVCodeServiceTest
 * 
 * @package Tests\Unit\Services
 */
class TVCodeServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var TvCodeService
     */
    private TvCodeService $tvCodeService;

    /**
     * @var User
     */
    private User $user;

    /**
     * @var TokenService
     */
    private TokenService $tokenService;

    /**
     * @var array
     */
    private array $createdCodes = [];

    /**
     * Test setup
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tokenService = $this->mock(TokenService::class);
        $this->tvCodeService = new TvCodeService($this->tokenService);
        $this->user = User::factory()->create();
    }

    /**
     * Test teardown
     * 
     * @return void
     */
    protected function tearDown(): void
    {        
        foreach ($this->createdCodes as $code) {
            Cache::forget('tv_code:' . $code);
        }
        
        parent::tearDown();
    }

    /**
     * Generate a test code for testing scenarios
     * 
     * @param Carbon|null $expires
     * 
     * @return string
     */
    private function generateTestCode(Carbon $expires = null, $status = false): string
    {
        $tvCode = AndroidTvCode::factory()
            ->forUser($this->user)
            ->create(['expires_at' => $expires ?? Carbon::now()->addMinutes(10)]);

        $this->cacheCode($tvCode, $status);
        $this->createdCodes[] = $tvCode->one_time_code;

        return $tvCode->one_time_code;
    }

    /**
     * Cache Code
     * 
     * @param AndroidTvCode $tvCode
     * @param bool $status
     * 
     * @return void
     */
    private function cacheCode(AndroidTvCode $tvCode, bool $status): void
    {
        Cache::put(
            'tv_code:' . $tvCode->one_time_code,
            [
                'user_id' => $this->user->id,
                'activated' => $status,
                'expires_at' => $tvCode->expires_at->timestamp
            ],
            $tvCode->expires_at
        );
    }

    #[Test]
    public function test_generates_valid_code(): void
    {
        $result = $this->tvCodeService->generateCode($this->user->email);

        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertEquals(6, strlen($result['code']));

        $this->assertDatabaseHas('android_tv_codes', [
            'user_id' => $this->user->id,
            'one_time_code' => $result['code']
        ]);

        $this->assertTrue(Cache::has('tv_code:' . $result['code']));
    }

    #[Test]
    public function test_poll_prevents_activation_with_wrong_token(): void
    {
        $code = $this->generateTestCode(Carbon::now()->addMinutes(5), false);
        $wrongUser = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Code does not belong to the authenticated user');
        
        $this->tvCodeService->activateCode($code, $wrongUser->id);
    }

    #[Test]
    public function test_polls_inactive_code():void
    {
        $code = $this->generateTestCode(Carbon::now()->addMinutes(5), false);
        $result = $this->tvCodeService->pollCode($code);
    
        $this->assertFalse($result['activated']);
        $this->assertArrayHasKey('expires_at', $result['data']);
        $this->assertArrayNotHasKey('token', $result['data']);
        
        $this->assertDatabaseHas('android_tv_codes', [
            'one_time_code' => $code
        ]);
    }

    #[Test]
    public function test_poll_throws_exception_when_code_is_invalid(): void
    {
        $code = $this->generateTestCode(Carbon::now()->subMinutes(60), false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Code not found or expired');
        
        $this->tvCodeService->pollCode($code);
    }

    #[Test]
    public function test_activates_valid_code(): void
    {
        $code = $this->generateTestCode(Carbon::now()->addMinutes(5), false);
        $cacheKey = 'tv_code:' . $code;

        $this->assertFalse(Cache::get($cacheKey)['activated']);

        $result = $this->tvCodeService->activateCode($code, $this->user->id);
        $cachedData = Cache::get($cacheKey);

        $this->assertTrue($result['activated']);
        $this->assertTrue(Cache::get('tv_code:' . $code)['activated']);
        $this->assertTrue($cachedData['activated']);
        $this->assertEquals($this->user->id, $cachedData['user_id']);

        $this->assertDatabaseHas('android_tv_codes', [
            'one_time_code' => $code,
            'user_id' => $this->user->id
        ]);
    }

    #[Test]
    public function test_polls_active_code_and_returns_token(): void
    {
        $code = $this->generateTestCode(Carbon::now()->addMinutes(5), true);
        
        $this->tokenService
            ->shouldReceive('issueTokensForUser')
            ->once()
            ->withArgs(function ($user, $scopes) {
                return $user->id === $this->user->id && $scopes === ['tv'];
            })
            ->andReturn(['access_token' => 'test_token']);

        $result = $this->tvCodeService->pollCode($code);

        $this->assertTrue($result['activated']);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals(['access_token' => 'test_token'], $result['data']['token']);
        $this->assertDatabaseMissing('android_tv_codes', [
            'one_time_code' => $code
        ]);
        $this->assertFalse(Cache::has('tv_code:' . $code));
    }
}
