<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth('sanctum')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ], 401);
        }

        $user = auth('sanctum')->user();

        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح بالوصول، صلاحية أدمن مطلوبة'
            ], 403);
        }

        return $next($request);
    }
}
