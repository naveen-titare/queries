<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $permission = 'view'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $modules = preg_split('/[|,]/', $module) ?: [$module];
        foreach ($modules as $moduleKey) {
            $moduleKey = trim($moduleKey);
            if ($moduleKey !== '' && $user->canAccessModule($moduleKey, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'You do not have permission to access this module.',
            'module' => $module,
            'permission' => $permission,
        ], 403);
    }
}
