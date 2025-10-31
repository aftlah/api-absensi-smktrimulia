<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = null, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'responseStatus' => true,
            'responseMessage' => $message,
            'responseData' => $data,
            // 'errors' => null
        ], $code);
    }

    public static function error(string $message = 'Error', $errors = null, int $code = 400)
    {
        return response()->json([
            'responseStatus' => false,
            'responseMessage' => $message,
            'responseData' => null,
            // 'errors' => $errors
        ], $code);
    }
}
