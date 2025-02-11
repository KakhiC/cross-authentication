<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\AuthLoggerService;
use App\Services\TokenService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Class AuthController - handles user registration, login, and token refresh requests
 * 
 * @package App\Http\Controllers\UserAuth
 */
class AuthController extends Controller
{
    /**
     * @var string
     */
    protected const USER_FIELD_NAME = 'name';

    /**
     * @var AuthLoggerService
     */
    protected AuthLoggerService $authLoggerService;

    /**
     * AuthController Constructor.
     * 
     * @param AuthLoggerService $authLoggerService
     */
    public function __construct(AuthLoggerService $authLoggerService)
    {
        $this->authLoggerService = $authLoggerService;
    }

    /**
     * Create a new user account
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate(rules: [
                self::USER_FIELD_NAME => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|confirmed',
            ]);

            User::create(attributes: [
                self::USER_FIELD_NAME => $request->name,
                'email' => $request->email,
                'password' => bcrypt(value: $request->password),
            ]);

            return response()->json(
                ['message' => sprintf('User %s successfully created', $request->name)],
                status: 200
            );
        } catch (ValidationException $e) {
            return $this->authLoggerService->handleException($e, 'validation', 'Validation failed');
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'registration', 'Registration failed');
        }
    }

    /**
     * Log the user in and return access and refresh tokens
     * 
     * @param Request $request
     * 
     * @return JsonResponse|mixed
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate(rules: [
                'email' => 'required|email',
                'password' => 'required|string'
            ]);
    
            if (!auth()->attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $user = auth()->user();
    
            return response()->json([
                'data' => [
                    'user' => [
                        'email' => $user->email,
                    ],
                    'token' => app(TokenService::class)->issueTokensForUser($user),
                ]
            ]);
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'login', 'Login failed');
        }
    }

    /**
     * Refresh request endpoint handler, returns new access and refresh tokens, requires the old refresh token
     * 
     * @param Request $request
     * 
     * @return mixed
     */
    public function refresh(Request $request): mixed
    {
        try {
            $request->validate([
                'refresh_token' => 'required|string'
            ]);
    
            return response()->json([
                'data' => [
                    'token' => app(TokenService::class)->refreshTokens(
                        $request->input('refresh_token')
                        )
                ]
            ]);
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'token refresh', 'Token refresh failed');
        }
    }
}
