<?php

namespace App\Support;

use Monolog\LogRecord;

class PrivacyLogProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;
        $context = $exception instanceof \Throwable ? [
            'exception_type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ] : [];

        return $record->with(
            message: $exception instanceof \Throwable ? 'Application exception' : 'Application log (details withheld for privacy)',
            context: $context,
            extra: []
        );
    }
}
