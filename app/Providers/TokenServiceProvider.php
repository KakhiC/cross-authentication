<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\TokenService;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;

/**
 * Class TokenServiceProvider
 * 
 * @package App\Providers
 */
class TokenServiceProvider extends ServiceProvider
{
    /**
     * Register the TokenService class
     * 
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(
                $app->make(TokenRepository::class),
                $app->make(RefreshTokenRepository::class)
            );
        });
    }
}
