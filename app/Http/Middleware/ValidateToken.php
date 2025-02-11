<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

/**
 * Class ValidateToken
 * 
 * @package App\Http\Middleware
 */
class ValidateToken
{
    /**
     * @var TokenRepository
     */
    private TokenRepository $tokenRepository;

    /**
     * ValidateToken Constructor.
     * 
     * @param TokenRepository $tokenRepository
     */
    public function __construct(
        TokenRepository $tokenRepository
    ) {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * Service handler for validating tokens and authenticating users
     * 
     * @param Request $request
     * @param Closure $next
     * 
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed 
    {
        try {
            $bearerToken = $request->bearerToken();
            
            if (!$bearerToken) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::file(config('passport.private_key'))
            );

            $token = $config->parser()->parse($bearerToken);
            $tokenClaims = $token->claims();

            if ($token->isExpired(new \DateTimeImmutable())) {
                return response()->json(['message' => 'Token expired'], 401);
            }

            $tokenId = $tokenClaims->get('jti');
            $dbToken = $this->tokenRepository->find($tokenId);
            
            if (!$dbToken || $dbToken->revoked || !in_array('mobile', $tokenClaims->get('scopes'))) {
                return response()->json(['message' => 'Token invalid or revoked'], 401);
            }

            $user = User::find($tokenClaims->get('user_id'));

            Auth::setUser($user);

            return $next($request);
        } catch (Exception $e) {
            return response()->json(['message' => 'Token validation failed'], 401);
        }
    }
}
