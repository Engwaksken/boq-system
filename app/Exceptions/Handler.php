<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'api_key',
        'secret',
        'token',
        'gateway_secret',
        'webhook_secret',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // API requests get JSON responses
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Render an exception for API requests.
     */
    protected function renderApiException($request, Throwable $e): JsonResponse
    {
        // Validation errors
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Authentication errors
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Unauthenticated. Please log in.',
            ], 401);
        }

        // Authorization errors
        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        // Model not found
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => "{$model} not found.",
            ], 404);
        }

        // Route not found
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'error_code' => 'NOT_FOUND',
                'message' => 'The requested resource was not found.',
            ], 404);
        }

        // Method not allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'error_code' => 'METHOD_NOT_ALLOWED',
                'message' => 'The HTTP method is not allowed for this route.',
            ], 405);
        }

        // HTTP exceptions (4xx, 5xx with custom messages)
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage();

            // Don't expose internal messages for 5xx errors
            if ($statusCode >= 500) {
                $this->logException($e, $request);
                return response()->json([
                    'success' => false,
                    'error_code' => 'SERVER_ERROR',
                    'message' => 'An unexpected error occurred. Please try again later.',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'message' => $message ?: 'An error occurred.',
            ], $statusCode);
        }

        // Database query errors
        if ($e instanceof QueryException) {
            $this->logException($e, $request);

            // Check for common constraint violations
            $errorCode = $e->errorInfo[1] ?? 0;
            $message = $this->getDatabaseErrorMessage($errorCode, $e->getMessage());

            return response()->json([
                'success' => false,
                'error_code' => 'DATABASE_ERROR',
                'message' => $message,
            ], 422);
        }

        // Expected business logic exceptions (custom exceptions with user-friendly messages)
        if ($e instanceof \App\Exceptions\BusinessException) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        // All other exceptions - log and return generic error
        $this->logException($e, $request);

        return response()->json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => 'An unexpected error occurred. Please try again later.',
        ], 500);
    }

    /**
     * Log exception with context but sanitize sensitive data.
     */
    protected function logException(Throwable $e, $request): void
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'organisation_id' => $request->user()?->organisation_id,
            'trace' => $e->getTraceAsString(),
        ];

        // Sanitize sensitive data from request
        $input = $request->except($this->dontFlash);
        $context['input'] = $this->sanitizeInput($input);

        \Log::error('API Exception', $context);
    }

    /**
     * Sanitize input array to remove sensitive fields.
     */
    protected function sanitizeInput(array $input): array
    {
        $sensitiveKeys = [
            'api_key', 'secret', 'token', 'password', 'password_confirmation',
            'current_password', 'gateway_secret', 'webhook_secret', 'api_secret',
            'private_key', 'access_token', 'refresh_token', 'idempotency_key',
        ];

        foreach ($input as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $input[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $input[$key] = $this->sanitizeInput($value);
            }
        }

        return $input;
    }

    /**
     * Get user-friendly message for common database errors.
     */
    protected function getDatabaseErrorMessage(int $errorCode, string $originalMessage): string
    {
        return match ($errorCode) {
            1062 => 'A record with this value already exists.', // Duplicate entry
            1451 => 'Cannot delete this record because it is referenced by other records.', // Foreign key constraint
            1452 => 'Referenced record does not exist.', // Foreign key constraint fails
            1048 => 'A required field is missing.', // Column cannot be null
            1064 => 'Invalid query syntax.', // SQL syntax error
            default => 'A database error occurred. Please try again.',
        };
    }
}