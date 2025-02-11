<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use DateTimeImmutable;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\RefreshTokenRepository;
use Laravel\Passport\Token;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * Class TokenService
 * 
 * @package App\Services
 */
class TokenService
{
    /**
     * @var Configuration
     */
    private Configuration $config;

    /**
     * @var int
     */
    private const ACCESS_TOKEN_EXPIRY_DAYS = 15;

    /**
     * @var int
     */
    private const REFRESH_TOKEN_EXPIRY_DAYS = 30;

    /**
     * TokenService Constructor.
     * 
     * @param TokenRepository $tokenRepository
     * @param RefreshTokenRepository $refreshTokenRepository
     */
    public function __construct(
        private TokenRepository $tokenRepository,
        private RefreshTokenRepository $refreshTokenRepository
    ) {
        $this->tokenRepository = $tokenRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::file(config('passport.private_key'))
        );
    }

    /**
     * Entry point for issuing tokens for a user
     * 
     * @param Authenticatable $user
     * @param array $scopes
     * 
     * @return array
     */
    public function issueTokensForUser(Authenticatable $user, array $scopes = ['mobile']): array
    {
        $client = $this->getOAuthClient();
        $token = $this->createAccessToken($user, $client, $scopes);
        $refreshToken = $this->createRefreshToken($token->id);

        return [
            'access_token' => $this->createJwtToken($token, $client),
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at->toDateTimeString(),
            'refresh_token' => $refreshToken->id
        ];
    }

    /**
     * Entry point for checking and revoking old tokens and generating new ones
     * 
     * @param string $refreshToken
     * 
     * @throws InvalidArgumentException
     * @return array
     */
    public function refreshTokens(string $refreshToken): array
    {
        $oldRefToken = $this->refreshTokenRepository->find($refreshToken);

        if (!$oldRefToken || $oldRefToken->revoked) {
            throw new InvalidArgumentException('Access token not found or is expired');
        }

        $oldAccessToken = $this->tokenRepository->find($oldRefToken->access_token_id);

        if (!$oldAccessToken) {
            throw new InvalidArgumentException('Associated access token not found');
        }

        Auth::setUser( User::find($oldAccessToken->user_id));
        $this->revokeTokens($oldAccessToken->id);

        return $this->issueTokensForUser(
            $oldRefToken->accessToken->user,
            $oldRefToken->accessToken->scopes
        );
    }

    /**
     * 
     * Generates an oauth access token
     * 
     * @param Authenticatable $user
     * @param Client $client
     * @param array $scopes
     * 
     * @return Token
     */
    private function createAccessToken(Authenticatable $user, Client $client, array $scopes): Token
    {
        return $this->tokenRepository->create([
            'id' => bin2hex(random_bytes(32)),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'name' => 'Public Client Token',
            'scopes' => $scopes,
            'revoked' => false,
            'expires_at' => now()->addDays(self::ACCESS_TOKEN_EXPIRY_DAYS),
        ]);
    }

    /**
     * Generates an oauth refresh token
     * 
     * @param string $accessTokenId
     * 
     * @return RefreshToken
     */
    private function createRefreshToken(string $accessTokenId): RefreshToken
    {
        return $this->refreshTokenRepository->create([
            'id' => bin2hex(random_bytes(40)),
            'access_token_id' => $accessTokenId,
            'revoked' => false,
            'expires_at' => now()->addDays(self::REFRESH_TOKEN_EXPIRY_DAYS),
        ]);
    }

    /**
     * Generates a JWT token for the third party client
     * 
     * @param Token $token
     * @param Client $client
     * 
     * @return string
     */
    private function createJwtToken(Token $token, Client $client): string
    {
        $now = new DateTimeImmutable();

        return $this->config->builder()
            ->issuedBy(config('app.url'))
            ->permittedFor((string)$client->id)
            ->identifiedBy($token->id)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt(DateTimeImmutable::createFromInterface($token->expires_at))
            ->withClaim('scopes', $token->scopes)
            ->withClaim('user_id', $token->user_id)
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    /**
     * Check if exists and get oauth client
     * 
     * @throws Exception
     * @return Client
     */
    private function getOAuthClient(): Client
    {
        $client = Client::where('name', 'cross-authentication')->first();

        if (!$client) {
            throw new Exception('OAuth client not found. Please check client configuration.');
        }

        return $client;
    }

    /**
     * Revokes the access token and the refresh token using the existing access token ID
     * 
     * @param string $accessTokenId
     * 
     * @return void
     */
    private function revokeTokens(string $accessTokenId): void
    {
        $this->tokenRepository->revokeAccessToken($accessTokenId);
        $this->refreshTokenRepository->revokeRefreshTokensByAccessTokenId($accessTokenId);
    }
}
