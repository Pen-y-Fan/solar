<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind the Cloudflare Tunnel, cloudflared is the only thing that
        // can ever reach this app (no ports are published to the host), so
        // trusting every hop that gets this far is safe and lets Laravel
        // see the real client IP from X-Forwarded-For instead of
        // cloudflared's internal docker address.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
