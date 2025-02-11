<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AndroidTvCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

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
     * TV Code expiration time
     * 
     * @var int
     */
    private const CODE_EXPIRY_MINUTES = 10;

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
            ['user_id' => $user->id],
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
        $codeData = $this->getCodeFromCache($code);
        $codeStatus = (bool) $codeData['activated'];
        $userId = $codeData['user_id'];
        
        $response = [
            'activated' => $codeStatus,
            'expires_at' => date('Y-m-d H:i:s', $codeData['expires_at'])
        ];

        if ($codeStatus) {
            $user = User::findOrFail($userId);
            $response['token'] = app(TokenService::class)->issueTokensForUser($user, ['tv']);

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
     * @return array{activated: bool, expires_at: string}
     */
    public function activateCode(string $code, int $userId): array
    {
        $codeData = $this->getCodeFromCache($code);

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
    private function getCodeFromCache(string $code): array
    {
        $codeData = Cache::get(self::CACHE_KEY_PREFIX . $code);
        
        if (!$codeData) {
            throw new InvalidArgumentException('Code not found or expired');
        }

        return $codeData;
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

        AndroidTvCode::where('user_id', $userId)
            ->where('one_time_code', $code)
            ->delete();
    }
}
