<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Throwable;

class StructuredJsonFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $context = $record->context;
        $exception = $context['exception'] ?? null;

        unset($context['exception']);

        return $this->toJson([
            'timestamp' => $record->datetime->format(DATE_ATOM),
            'level' => strtolower($record->level->getName()),
            'message' => $record->message,
            'user_id' => $this->userId($record),
            'route' => $this->route($record),
            'request_id' => $this->requestId($record),
            'context' => (object) $context,
            'exception' => $this->exception($exception),
        ], true)."\n";
    }

    private function userId(LogRecord $record): mixed
    {
        return $record->context['user_id']
            ?? request()?->attributes->get('user_id')
            ?? request()?->user()?->id;
    }

    private function route(LogRecord $record): ?string
    {
        return $record->context['route']
            ?? request()?->attributes->get('route')
            ?? request()?->route()?->getName()
            ?? request()?->path();
    }

    private function requestId(LogRecord $record): ?string
    {
        return $record->context['request_id']
            ?? request()?->attributes->get('request_id')
            ?? request()?->header('X-Request-Id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function exception(mixed $exception): ?array
    {
        if (! $exception instanceof Throwable) {
            return null;
        }

        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }
}
