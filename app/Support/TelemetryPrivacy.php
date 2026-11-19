<?php

namespace App\Support;

use Sentry\Event;
use Sentry\EventType;
use Sentry\ExceptionDataBag;
use Sentry\Stacktrace;

class TelemetryPrivacy
{
    public static function beforeSend(Event $event): ?Event
    {
        if ($event->getType() !== EventType::event()) return null;

        // Whitelist a new event: SQL error text and arbitrary context can contain private data.
        $clean = Event::createEvent($event->getId());
        $clean->setTimestamp($event->getTimestamp());
        $clean->setLevel($event->getLevel());
        $clean->setEnvironment(config('sentry.environment'));
        $clean->setRelease(config('sentry.release'));
        $clean->setServerName('meuportfolio');
        $clean->setMessage('Application error (details withheld for privacy).');
        $clean->setExceptions(array_map(function ($exception) {
            return (new ExceptionDataBag(
                new \RuntimeException('Details withheld for privacy.'),
                self::stacktrace($exception->getStacktrace())
            ))->setType($exception->getType());
        }, $event->getExceptions()));
        $clean->setStacktrace(self::stacktrace($event->getStacktrace()));
        $route = app()->bound('request') ? app('request')->route() : null;
        if ($route instanceof \Illuminate\Routing\Route) {
            $clean->setTransaction($route->getName() ?: $route->uri());
        }
        return $clean;
    }

    public static function dropTransaction(Event $event): ?Event
    {
        return null;
    }

    private static function stacktrace(?Stacktrace $stack): ?Stacktrace
    {
        if (!$stack) return null;
        $frames = array_map(function ($frame) {
            $frame = clone $frame;
            $frame->setVars([])->setPreContext([])->setPostContext([])->setContextLine(null);
            return $frame;
        }, $stack->getFrames());
        return new Stacktrace($frames);
    }
}
