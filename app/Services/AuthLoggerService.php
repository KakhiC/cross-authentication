<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

/**
 * Class AuthLoggerService
 * 
 * @package App\Services
 */
class AuthLoggerService
{
    /**
     * Logs an exception, and generates a response
     * 
     * @param Exception $e
     * @param string $action
     * @param string $message
     * 
     * @return JsonResponse
     */
    public function handleException(Exception $e, string $action, string $message = ''): JsonResponse
    {
        $context = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        $this->logException($action, $e, $context);

        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        return response()->json([
            'message' => $message ?: 'An error occurred',
            'error' => $e->getMessage()
        ], 500);
    }

    /**
     * Logs an exception
     * 
     * @param string $action
     * @param Exception $e
     * @param array $context
     * 
     * @return void
     */
    public function logException(string $action, Exception $e, array $context = []): void
    {
        Log::channel('daily')->error("Auth {$action} failed", [
            'action' => $action,
            'exception' => [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e)
            ],
            'context' => array_merge($context, [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ])
        ]);
    }
}
