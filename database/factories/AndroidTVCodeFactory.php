<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AndroidTvCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Class AndroidTvCodeFactory
 * 
 * @package Database\Factories
 */
class AndroidTvCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     * 
     * @var 
     */
    protected $model = AndroidTvCode::class;

    /**
     * @return array
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'one_time_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    /**
     * Expired State
     * 
     * @return AndroidTvCodeFactory
     */
    public function expired(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subMinutes(10),
            ];
        });
    }

    /**
     * @param User $user
     * 
     * @return AndroidTvCodeFactory
     */
    public function forUser(User $user): self
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user->id,
            ];
        });
    }
}
