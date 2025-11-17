<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && $user->tokenCan('admin')) {
            return $next($request);
        }
        
        return response()->json([
            "success" => false, 
            "message" => "Acceso denegado. Se requieren permisos de administrador."
        ], 403);
    }
}
