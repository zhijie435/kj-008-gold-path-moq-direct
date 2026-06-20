<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function respond($data = null, string $message = 'success', int $httpStatus = 200): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ], $httpStatus);
    }

    protected function respondCreated($data, string $message = '创建成功'): JsonResponse
    {
        return $this->respond($data, $message, 201);
    }

    protected function respondPaginated($paginator, string $message = 'success', array $extra = []): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => array_merge([
                'list' => $paginator->items(),
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
            ], $extra),
        ]);
    }
}
