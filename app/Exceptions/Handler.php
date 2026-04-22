<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * @var list<string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function context(): array
    {
        $request = request();

        return array_filter([
            'user_id' => $request?->attributes->get('user_id') ?? $request?->user()?->id,
            'route' => $request?->attributes->get('route') ?? $request?->route()?->getName() ?? $request?->path(),
            'request_id' => $request?->attributes->get('request_id') ?? $request?->header('X-Request-Id'),
        ], static fn ($value): bool => $value !== null);
    }

    public function report(Throwable $e): void
    {
        parent::report($e);
    }
}
