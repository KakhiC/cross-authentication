<?php 

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

/**
 * Class AndroidTVCodeTest
 * 
 * @package Tests\Feature\Auth
 */
class AndroidTVCodeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @var User
     */
    private User $user;

    /**
     * @var Client
     */
    private Client $client;

    /**
     * @var array
     */
    private array $generatedCodes = [];

    /**
     * Test setup
     * 
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'testfeature@test.com',
            'password' => bcrypt('password12345')
        ]);

        $this->client = Client::factory()->create([
            'name' => 'cross-authentication-test'
        ]);
    }

    /**
     * Test teardown - drop all the new cached codes
     * 
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->generatedCodes as $code) {
            Cache::forget('tv_code:' . $code);
        }
        
        parent::tearDown();
    }

    /**
     * Generate a test TV code
     * 
     * return TestResponse
     */
    protected function generateTVCode(string $email = "testfeature@test.com"): TestResponse
    {
        $response = $this->postJson('/api/generate-tv-code', [
            'email' => $email
        ]);

        $this->generatedCodes[] = $response->json('data.code');

        return $response;
    }

    #[Test]
    public function test_can_generate_tv_code(): void
    {
        $this->generateTVCode()
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'code',
                    'expires_at'
                ]
            ]);
    }

    #[Test]
    public function test_cannot_generate_code_for_invalid_email(): void
    {
        $this->generateTVCode('nonexistent@test.com')->assertStatus(500);
    }

    #[Test]
    public function test_can_poll_pending_code(): void
    {
        $generateResponse = $this->postJson('/api/generate-tv-code', [
            'email' => 'testfeature@test.com'
        ]);

        $code = $generateResponse->json('data.code');
        $response = $this->postJson("/api/poll-tv-code", ['code' => $code]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'code',
                    'activated',
                    'data' => [
                        'expires_at'
                    ]
                ]
            ])
            ->assertJson([
                'data' => [
                    'activated' => false,
                    'data' => [
                        'expires_at' => $response->json('data.data.expires_at')
                    ]
                ]
            ]);
    }

    #[Test]
    public function test_cannot_activate_code_without_auth(): void
    {
        $generateResponse = $this->generateTVCode();

        $code = $generateResponse->json('data.code');

        $this->postJson('/api/active-tv-code', [
            'code' => $code
        ])->assertStatus(401);
    }
}
