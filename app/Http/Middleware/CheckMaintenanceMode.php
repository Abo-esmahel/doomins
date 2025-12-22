<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isDownForMaintenance()) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'النظام قيد الصيانة',
                    'retry_after' => 300, // 5 دقائق
                ], 503);
            }
        }

        return $next($request);
    }
}
