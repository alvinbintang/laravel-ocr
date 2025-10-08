<?php

namespace App\Services\Shared;

use Illuminate\Http\JsonResponse;

class ApiResponseService
{
    /**
     * Return a success JSON response with consistent structure.
     *
     * @param mixed|null $data
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    public function success(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $code);
    }

    /**
     * Return a generic error JSON response with consistent structure.
     *
     * @param string $message
     * @param int $code
     * @param mixed|null $errors
     * @return JsonResponse
     */
    public function error(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Return a 500 Internal Server Error response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function internalServerError(string $message = 'Internal Server Error'): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 500
        );
    }

    /**
     * Return a 422 Validation Error response.
     *
     * @param mixed $errors
     * @param string $message
     * @return JsonResponse
     */
    public function validationError(mixed $errors, string $message = 'Validation Error'): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 422,
            errors: $errors
        );
    }

    /**
     * Return a 404 Not Found response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function notFound(string $message = 'Not Found'): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 404
        );
    }

    /**
     * Return a 401 Unauthorized response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 401
        );
    }

    /**
     * Return a 403 Forbidden response.
     *
     * @param string $message
     * @param mixed|null $errors
     * @return JsonResponse
     */
    public function forbidden(string $message = 'Forbidden', mixed $errors = null): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 403,
            errors: $errors
        );
    }

    /**
     * Return a 204 No Content response with optional message.
     *
     * @param string $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public function noContent(string $message = 'No Content', int $statusCode = 204): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => null,
            'errors' => null,
        ], $statusCode);
    }

    /**
     * Return a 201 Created response.
     *
     * @param mixed|null $data
     * @param string $message
     * @return JsonResponse
     */
    public function created(mixed $data = null, string $message = 'Resource Created'): JsonResponse
    {
        return $this->success(
            data: $data,
            message: $message,
            code: 201
        );
    }

    /**
     * Return a 409 Conflict response.
     *
     * @param string $message
     * @param mixed|null $errors
     * @return JsonResponse
     */
    public function conflict(string $message = 'Conflict', mixed $errors = null): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 409,
            errors: $errors
        );
    }

    /**
     * Return a 429 Too Many Requests response.
     *
     * @param string $message
     * @return JsonResponse
     */
    public function tooManyRequests(string $message = 'Too many attempts. Please try again later.'): JsonResponse
    {
        return $this->error(
            message: $message,
            code: 429
        );
    }
}