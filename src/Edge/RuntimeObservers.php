<?php

namespace Native\Mobile\Edge;

use Native\Mobile\Edge\Contracts\RuntimeObserver;
use Throwable;

/**
 * Static fan-out for opt-in native runtime observers.
 *
 * Every hot-path call is guarded by any(), so an application without an
 * observer pays only an empty-registry check and allocates no snapshots.
 */
class RuntimeObservers
{
    /** @var array<int, RuntimeObserver> */
    protected static array $observers = [];

    protected static int $sequence = 0;

    public static function register(RuntimeObserver $observer): int
    {
        $id = ++static::$sequence;
        static::$observers[$id] = $observer;

        return $id;
    }

    public static function unregister(int $id): void
    {
        unset(static::$observers[$id]);
    }

    public static function any(): bool
    {
        return static::$observers !== [];
    }

    public static function reset(): void
    {
        static::$observers = [];
        static::$sequence = 0;
    }

    public static function componentPublished(array $snapshot): void
    {
        static::notify(fn (RuntimeObserver $observer) => $observer->componentPublished($snapshot));
    }

    public static function dispatchStarting(array $dispatch): void
    {
        static::notify(fn (RuntimeObserver $observer) => $observer->dispatchStarting($dispatch));
    }

    public static function dispatchFinished(array $dispatch): void
    {
        static::notify(fn (RuntimeObserver $observer) => $observer->dispatchFinished($dispatch));
    }

    public static function failed(Throwable $exception, array $context): void
    {
        static::notify(fn (RuntimeObserver $observer) => $observer->failed($exception, $context));
    }

    protected static function notify(callable $notification): void
    {
        foreach (static::$observers as $observer) {
            try {
                $notification($observer);
            } catch (Throwable) {
                // Instrumentation must never change application behavior.
            }
        }
    }
}
