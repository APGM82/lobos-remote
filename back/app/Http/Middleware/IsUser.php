<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user && $user->tokenCan('user')) {
            return $next($request);
        }
        
        return response()->json([
            "success" => false, 
            "message" => "Acceso denegado. Debe estar autenticado."
        ], 403);
    }
}
