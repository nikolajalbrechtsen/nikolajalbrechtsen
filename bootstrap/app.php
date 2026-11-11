<?php

use App\Http\Middleware\DetectHttpsProxy;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RegistrationEnabled;
use App\Http\Middleware\TrackVisitor;
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
        // The site runs behind Nginx (and maybe a reverse proxy / tunnel in front of Proxmox).
        $middleware->trustProxies(at: '*');
        $middleware->append(DetectHttpsProxy::class); // all routes, incl. the admin panel

        $middleware->web(append: [
            EnsureUserIsActive::class,
            TrackVisitor::class,
        ]);

        $middleware->alias([
            'registration' => RegistrationEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
