<?php

declare(strict_types= 1);

namespace App\Http\Controllers\TV;

use App\Http\Controllers\Controller;
use App\Services\TvCodeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * TVCodeController Constructor.
     * 
     * @param TvCodeService $tvCodeService
     */
    public function __construct(
        TvCodeService $tvCodeService
    ) {
        $this->tvCodeService = $tvCodeService;
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
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to generate TV code',
                'error' => $e->getMessage()
            ], 500);
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
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to check code status',
                'error' => $e->getMessage()
            ], 500);
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

            $result = $this->tvCodeService->activateCode(
                $request->input('code'), 
                Auth::id()
            );

            return response()->json([
                'message' => 'TV code activated successfully',
                'data' => $result
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to activate TV code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
