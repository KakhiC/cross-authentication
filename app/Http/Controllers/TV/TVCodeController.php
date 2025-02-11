<?php

declare(strict_types= 1);

namespace App\Http\Controllers\TV;

use App\Services\AuthLoggerService;
use App\Services\TvCodeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Class TVCodeController
 * 
 * @package App\Http\Controllers\TV
 */
class TVCodeController extends Controller
{
    /**
     * @var string
     */
    private const CACHE_KEY_PREFIX = 'tv_code:';
    
    /**
     * @var int
     */
    private const CODE_EXPIRY_MINUTES = 10;

    /**
     * @var TvCodeService
     */
    private TvCodeService $tvCodeService;

    /**
     * @var AuthLoggerService
     */
    protected AuthLoggerService $authLoggerService;

    /**
     * TVCodeController Constructor.
     * 
     * @param TvCodeService $tvCodeService
     * @param AuthLoggerService $authLoggerService
     */
    public function __construct(
        TvCodeService $tvCodeService,
        AuthLoggerService $authLoggerService
    ) {
        $this->tvCodeService = $tvCodeService;
        $this->authLoggerService = $authLoggerService;
    }

    /**
     * Handles the generate-tv-code post request
     * 
     * @param Request $request
     * 
     * @return JsonResponse|mixed
     */
    public function generate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email'
            ]);

            $result = $this->tvCodeService->generateCode($request->email);

            return response()->json(['data' => $result], 201);
        } catch (ValidationException $e) {
            return $this->handleValidationError($e);
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'code generation', 'Failed to generate TV code');
        }
    }

    /**
     * Handles polling requests.
     * 
     * @param Request $request
     * 
     * @return JsonResponse|mixed
     */
    public function poll(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6'
            ]);

            $result = $this->tvCodeService->pollCode($request->input('code'));

            return response()->json(['data' => $result]);
        } catch (ValidationException $e) {
            return $this->handleValidationError($e);
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'code status check', 'Failed to check code status');
        }
    }

    /**
     * Handles the activate-tv-code post request
     * 
     * @param Request $request
     * 
     * @return JsonResponse|mixed
     */
    public function activate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string|size:6'
            ]);

            return response()->json([
                'message' => 'TV code activated successfully',
                'data' => $this->tvCodeService->activateCode(
                    $request->input('code'), 
                    Auth::id()
                )
            ]);
        } catch (ValidationException $e) {
            return $this->handleValidationError($e);
        } catch (Exception $e) {
            return $this->authLoggerService->handleException($e, 'code activation', 'Failed to activate TV code');
        }
    }

    /**
     * Handles validation exceptions
     * 
     * @param Exception $e
     * 
     * @return JsonResponse
     */
    private function handleValidationError(ValidationException $e): JsonResponse
    {
        return $this->authLoggerService->handleException($e, 'validation', 'Validation failed');
    }
}
