<?php

namespace App\Traits;

trait ApiResponseTrait
{
    /**
     * استجابة ناجحة
     */
    protected function successResponse($data = null, string $message = '', int $code = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * استجابة فاشلة
     */
    protected function errorResponse(string $message = '', int $code = 400, $errors = null): \Illuminate\Http\JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * استجابة للبيانات المجدولة
     */
    protected function paginatedResponse($data, string $message = ''): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ]
        ]);
    }
}
