<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'user' => \App\Http\Middleware\IsUser::class,
        ]);
        
        // Habilitar CORS para todas las rutas (API y broadcasting)
        $middleware->api(prepend: [
            \App\Http\Middleware\Cors::class,
        ]);
        
        // También aplicar CORS a las rutas web (para broadcasting/auth)
        $middleware->web(prepend: [
            \App\Http\Middleware\Cors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
