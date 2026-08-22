<?php

use App\Support\UploadLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applies the signed-in user's saved UI language to every web request.
        $middleware->web(append: [
            App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'admin' => App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        /*
         | Trust the forwarding headers when the app is reached through a
         | tunnel or a load balancer.
         |
         | Such a proxy terminates HTTPS and forwards plain HTTP, so without
         | this Laravel believes the request is insecure: asset() and route()
         | emit http:// URLs onto an https:// page, the browser blocks them as
         | mixed content, and the CSS and JS silently fail to load. Reading
         | X-Forwarded-Proto/Host also makes generated URLs use the public
         | hostname rather than localhost, which matters because a quick
         | tunnel gets a different hostname every time it restarts.
         |
         | '*' is appropriate here because the only thing in front of this app
         | is the tunnel itself; behind a real load balancer, name it instead.
         */
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         | PHP rejects a request larger than post_max_size before any of our
         | validation runs, so an over-sized upload would otherwise surface as
         | a raw 413 stack trace. Turn it into the same kind of message the
         | form's own validation would have produced.
         |
         | Note the request is discarded by PHP at this point: $request->all()
         | and the uploaded files are empty, and even the CSRF token is gone,
         | so the only safe destination is the referring page.
         */
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = sprintf(
                'That upload is too large for this server. The whole request must be under %s, '
                .'and each PDF under %s. Try fewer or smaller files.',
                UploadLimits::perRequestLabel(),
                UploadLimits::perFileLabel(),
            );

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 413);
            }

            return redirect()
                ->to($request->headers->get('referer') ?: route('papers.create'))
                ->with('error', $message);
        });

        $exceptions->shouldRenderJsonWhen(
            // The AI, highlight and notification-count endpoints are consumed by
            // fetch() rather than by a browser navigation. Restricting JSON
            // rendering to `api/*` made a validation error on those routes come
            // back as an HTML redirect, which the front end cannot parse.
            fn (Request $request) => $request->is('api/*')
                || $request->is('ai/*')
                || $request->expectsJson(),
        );
    })->create();
