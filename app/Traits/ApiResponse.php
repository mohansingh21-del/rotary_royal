<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Standard success response structure.
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = 200, array $extra = []): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        return response()->json($response, $status);
    }

    /**
     * Standard created success response structure.
     */
    protected function createdResponse($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Standard no content success response structure.
     */
    protected function noContentResponse(string $message = 'Resource deleted successfully'): JsonResponse
    {
        return $this->successResponse(null, $message, 200); // 200 is often preferred in simple APIs to show a message, 204 has no body.
    }

    /**
     * Standard error response structure.
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Standard unauthorized error response structure.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Standard forbidden error response structure.
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Standard not found error response structure.
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Standard conflict error response structure.
     */
    protected function conflictResponse(string $message = 'Conflict structure'): JsonResponse
    {
        return $this->errorResponse($message, 409);
    }

    /**
     * Standard validation error response structure.
     */
    protected function validationResponse($errors = null, string $message = 'Validation error'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }
}
