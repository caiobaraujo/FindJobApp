<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('route', $this->resolveRoute($request));

        if ($request->user() !== null) {
            $request->attributes->set('user_id', $request->user()->id);
        }

        Log::withContext($this->context($request));

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $requestId = $request->header('X-Request-Id');

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        return (string) Str::uuid();
    }

    private function resolveRoute(Request $request): string
    {
        $route = $request->route();

        if ($route === null) {
            return $request->path();
        }

        return $route->getName() ?? $route->uri();
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        return [
            'request_id' => $request->attributes->get('request_id'),
            'route' => $request->attributes->get('route'),
            'user_id' => $request->attributes->get('user_id'),
        ];
    }
}
