<?php

namespace Native\Mobile\Edge\Contracts;

use Throwable;

/**
 * Opt-in observer for the native component runtime.
 *
 * Core does not register an observer. Developer tools, recorders, and other
 * packages may attach one through RuntimeObservers when they need component
 * state, dispatch timing, or failure information beyond the published tree.
 */
interface RuntimeObserver
{
    /** A component frame was published. */
    public function componentPublished(array $snapshot): void;

    /** A UI or native event is about to be dispatched. */
    public function dispatchStarting(array $dispatch): void;

    /** A UI or native event finished dispatching. */
    public function dispatchFinished(array $dispatch): void;

    /** The component runtime caught a failure and is about to render its error UI. */
    public function failed(Throwable $exception, array $context): void;
}
