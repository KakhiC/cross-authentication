<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\TokenService;
use DateTimeImmutable;
use Exception;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\RefreshTokenRepository;
use Laravel\Passport\Token;
use Laravel\Passport\TokenRepository;
use Tests\TestCase;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Class TokenServiceTest
 * 
 * @package Tests\Unit
 */
class TokenServiceTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var TokenService
     */
    private TokenService $tokenService;

    /**
     * @var TokenRepository
     */
    private TokenRepository $tokenRepository;

    /**
     * @var RefreshTokenRepository
     */
    private RefreshTokenRepository $refreshTokenRepository;

    /**
     * @var User
     */
    private User $user;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * Test setup
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenRepository = $this->mock(TokenRepository::class);
        $this->refreshTokenRepository = $this->mock(RefreshTokenRepository::class);
        
        $this->tokenService = new TokenService(
            $this->tokenRepository,
            $this->refreshTokenRepository
        );

        $this->user = User::factory()->create();
        $this->client = Client::factory()->create(['name' => 'cross-authentication']);
    }

    #[Test]
    public function test_tokens_issued_for_valid_users(): void
    {
        $accessToken = new Token([
            'id' => 'test_token_id',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'name' => 'Public Client Token',
            'scopes' => ['mobile'],
            'revoked' => false,
            'expires_at' => now()->addDays(15)
        ]);

        $refreshToken = new RefreshToken([
            'id' => 'test_refresh_token',
            'access_token_id' => $accessToken->id,
            'revoked' => false,
            'expires_at' => now()->addDays(30)
        ]);

        $this->tokenRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($accessToken);

        $this->refreshTokenRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($refreshToken);

        $result = $this->tokenService->issueTokensForUser($this->user);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals($refreshToken->id, $result['refresh_token']);
    }

    #[Test]
    public function test_invalid_refresh_token_throws_exception(): void
    {
        $this->refreshTokenRepository
            ->shouldReceive('find')
            ->with('invalid_token')
            ->once()
            ->andReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access token not found or is expired');

        $this->tokenService->refreshTokens('invalid_token');
    }

    #[Test]
    public function test_revoked_refresh_token_throws_exception(): void
    {
        $revokedToken = (object)[
            'id' => 'revoked_token',
            'revoked' => true
        ];

        $this->refreshTokenRepository
            ->shouldReceive('find')
            ->with($revokedToken->id)
            ->once()
            ->andReturn($revokedToken);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Access token not found or is expired');

        $this->tokenService->refreshTokens($revokedToken->id);
    }

    #[Test]
    public function test_issues_new_tokens_with_valid_refresh_token(): void
    {
        $oldAccessToken = new Token([
            'id' => 'old_token_id',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'name' => 'Public Client Token',
            'scopes' => ['mobile'],
            'revoked' => false,
            'expires_at' => now()->addDays(15)
        ]);
    
        $oldRefreshToken = new RefreshToken([
            'id' => 'old_refresh_token',
            'access_token_id' => $oldAccessToken->id,
            'revoked' => false,
            'expires_at' => now()->addDays(30)
        ]);

        $oldAccessToken->user = $this->user;
        $oldRefreshToken->accessToken = $oldAccessToken;

        $this->refreshTokenRepository
            ->shouldReceive('find')
            ->with($oldRefreshToken->id)
            ->once()
            ->andReturn($oldRefreshToken);

        $this->tokenRepository
            ->shouldReceive('find')
            ->with($oldAccessToken->id)
            ->once()
            ->andReturn($oldAccessToken);

        $this->tokenRepository
            ->shouldReceive('revokeAccessToken')
            ->with($oldAccessToken->id)
            ->once();

        $this->refreshTokenRepository
            ->shouldReceive('revokeRefreshTokensByAccessTokenId')
            ->with($oldAccessToken->id)
            ->once();

        // New token creation
        $newAccessToken = new Token([
            'id' => 'new_token_id',
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'name' => 'Public Client Token',
            'scopes' => ['mobile'],
            'revoked' => false,
            'expires_at' => now()->addDays(15)
        ]);
    
        $newRefreshToken = new RefreshToken([
            'id' => 'new_refresh_token',
            'access_token_id' => $newAccessToken->id,
            'revoked' => false,
            'expires_at' => now()->addDays(30)
        ]);
    
        $this->tokenRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newAccessToken);
    
        $this->refreshTokenRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($newRefreshToken);
    
        $result = $this->tokenService->refreshTokens($oldRefreshToken->id);
    
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals($newRefreshToken->id, $result['refresh_token']);
        $this->assertNotEquals($oldRefreshToken->id, $result['refresh_token']);
    }
}
