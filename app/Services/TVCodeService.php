<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AndroidTvCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use App\Services\TokenService;

/**
 * Class TvCodeService
 * 
 * @package App\Services
 */
class TvCodeService
{
    /**
     * Prefix for caching
     * 
     * @var string
     */
    private const CACHE_KEY_PREFIX = 'tv_code:';

    /**
     * @var TokenService
     */
    private TokenService $tokenService;

    /**
     * TV Code expiration time
     * 
     * @var int
     */
    private const CODE_EXPIRY_MINUTES = 10;

    /**
     * TvCodeService Constructor.
     * 
     * @param TokenService $tokenService
     */
    public function __construct(
        TokenService $tokenService
    ) {
        $this->tokenService = $tokenService;
    }

    /**
     * Generate a code and associate it with an existing user
     * 
     * @param string $email
     * 
     * @return array
     */
    public function generateCode(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $code = $this->createUniqueCode();
        
        $tvCode = AndroidTvCode::updateOrCreate(
            [
                'user_id' => $user->id,
                'activated' => false
            ],
            [
                'one_time_code' => $code,
                'expires_at' => now()->addMinutes(self::CODE_EXPIRY_MINUTES),
            ]
        );

        $this->cacheCode($code, $user, $tvCode);

        return [
            'code' => $code,
            'expires_at' => $tvCode->expires_at->toDateTimeString()
        ];
    }

    /**
     * Entry point for polling a TV code
     * 
     * @param string $code
     * 
     * @return array
     */
    public function pollCode(string $code): array
    {
        $codeData = $this->getCode($code);
        $codeStatus = (bool) $codeData['activated'];
        $userId = $codeData['user_id'];
        
        $response = [
            'code' => $code,
            'activated' => $codeStatus,
            'data' => [
                'expires_at' => date('Y-m-d H:i:s', $codeData['expires_at'])
            ]
        ];

        if ($codeStatus) {
            $user = User::findOrFail($userId);
            $response['data']['token'] = $this->tokenService->issueTokensForUser($user, ['tv']);

            $this->dropUsedTVCode($code, $userId);
        }

        return $response;
    }

    /**
     * Activate the code by updating the cached entry
     * 
     * @param string $code
     * @param int $userId
     * 
     * @throws InvalidArgumentException
     * @return array
     */
    public function activateCode(string $code, int $userId): array
    {
        $codeData = $this->getCode($code);

        if ($codeData['user_id'] !== $userId) {
            throw new InvalidArgumentException('Code does not belong to the authenticated user');
        }

        $this->markCodeAsActivated($code, $codeData);

        return [
            'activated' => true,
            'expires_at' => date('Y-m-d H:i:s', $codeData['expires_at'])
        ];
    }

    /**
     * Generate a unique TV code
     * 
     * @return string
     */
    private function createUniqueCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (AndroidTvCode::where('one_time_code', $code)->exists());

        return $code;
    }

    /**
     * Cache a generated TV code after its initial generation
     * 
     * This currently caches data in the db, ideally, we would use redis to cache these to retreive them faster, without needing
     * to open the db connection for every poll request.
     * 
     * @param string $code
     * @param User $user
     * @param AndroidTvCode $tvCode
     * 
     * @return void
     */
    private function cacheCode(string $code, User $user, AndroidTvCode $tvCode): void
    {
        Cache::put(
            self::CACHE_KEY_PREFIX . $code,
            [
                'user_id' => $user->id,
                'activated' => false,
                'expires_at' => $tvCode->expires_at->timestamp
            ],
            $tvCode->expires_at
        );
    }

    /**
     * Fetch cached code data
     * 
     * @param string $code
     * 
     * @throws InvalidArgumentException
     * @return array
     */
    private function getCode(string $code): array
    {
        $codeData = Cache::get(self::CACHE_KEY_PREFIX . $code);
        
        if ($codeData) {
            return $codeData;
        }

        $tvCode = AndroidTvCode::where('one_time_code', $code)
            ->where('expires_at', '>', now())
            ->first();

        if (!$tvCode) {
            throw new InvalidArgumentException('Code not found or expired');
        }

        $this->cacheCode($code, User::findOrFail($tvCode->user_id), $tvCode);

        return [
            'user_id' => $tvCode->user_id,
            'activated' => (bool)$tvCode->activated,
            'expires_at' => $tvCode->expires_at->timestamp
        ];
    }

    /**
     * Activate the code after a successful activation by the user.
     * 
     * @param string $code
     * @param array $codeData
     * 
     * @return void
     */
    private function markCodeAsActivated(string $code, array $codeData): void
    {
        Cache::lock(self::CACHE_KEY_PREFIX . $code, 10)->block(5, function () use ($code, $codeData) {
            return Cache::put(
                self::CACHE_KEY_PREFIX . $code,
                array_merge($codeData, ['activated' => true]),
                Carbon::createFromTimestamp($codeData['expires_at'])
            );
        });
    }

    /**
     * Remove used TV codes after successfully getting polled, 
     * as it no longer serves any purpose
     * 
     * @param string $code
     * @param int $userId
     * 
     * @return void
     */
    private function dropUsedTVCode(string $code, int $userId): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $code);

        AndroidTvCode::where([
            'user_id' => $userId,
            'one_time_code' => $code,
            'activated' => false
        ])->delete();
    }
}
