<?php

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Contracts\RuntimeObserver;
use Native\Mobile\Edge\Elements\Column;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\NativeEventHandlers;
use Native\Mobile\Edge\RuntimeObservers;

class RuntimeObservedComponent extends NativeComponent
{
    public int $count = 0;

    public function render(): Column
    {
        return Column::make();
    }

    public function initializeCallbacks(): int
    {
        $this->nativeCallbacks = new CallbackRegistry;

        return $this->nativeCallbacks->register('increment');
    }

    public function dispatchUi(array $event): void
    {
        $this->dispatch($event);
    }

    public function dispatchPluginEvent(string $event, array $payload): void
    {
        $this->dispatchNativeEvent([
            'type' => self::EVENT_NATIVE,
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    public function increment(): void
    {
        $this->count++;
    }
}

afterEach(function () {
    RuntimeObservers::reset();
    NativeEventHandlers::reset();
});

function runtimeObserverSpy(): RuntimeObserver
{
    return new class implements RuntimeObserver
    {
        public array $published = [];

        public array $starting = [];

        public array $finished = [];

        public array $failures = [];

        public function componentPublished(array $snapshot): void
        {
            $this->published[] = $snapshot;
        }

        public function dispatchStarting(array $dispatch): void
        {
            $this->starting[] = $dispatch;
        }

        public function dispatchFinished(array $dispatch): void
        {
            $this->finished[] = $dispatch;
        }

        public function failed(Throwable $exception, array $context): void
        {
            $this->failures[] = [$exception, $context];
        }
    };
}

it('fans runtime notifications out and supports explicit unregistration', function () {
    $observer = runtimeObserverSpy();
    $id = RuntimeObservers::register($observer);

    RuntimeObservers::componentPublished(['class' => 'Example']);
    RuntimeObservers::dispatchStarting(['kind' => 'interaction']);
    RuntimeObservers::dispatchFinished(['durationMs' => 1.5]);
    RuntimeObservers::failed(new RuntimeException('broken'), ['kind' => 'failure']);

    expect(RuntimeObservers::any())->toBeTrue()
        ->and($observer->published)->toHaveCount(1)
        ->and($observer->starting)->toHaveCount(1)
        ->and($observer->finished[0]['durationMs'])->toBe(1.5)
        ->and($observer->failures[0][0]->getMessage())->toBe('broken');

    RuntimeObservers::unregister($id);

    expect(RuntimeObservers::any())->toBeFalse();
});

it('isolates application behavior from observer failures', function () {
    RuntimeObservers::register(new class implements RuntimeObserver
    {
        public function componentPublished(array $snapshot): void
        {
            throw new RuntimeException('observer');
        }

        public function dispatchStarting(array $dispatch): void
        {
            throw new RuntimeException('observer');
        }

        public function dispatchFinished(array $dispatch): void
        {
            throw new RuntimeException('observer');
        }

        public function failed(Throwable $exception, array $context): void
        {
            throw new RuntimeException('observer');
        }
    });

    RuntimeObservers::componentPublished([]);
    RuntimeObservers::dispatchStarting([]);
    RuntimeObservers::dispatchFinished([]);
    RuntimeObservers::failed(new RuntimeException('application'), []);

    expect(true)->toBeTrue();
});

it('dispatches claimed namespaced native events to package handlers', function () {
    $component = new class extends NativeComponent
    {
        public int $value = 0;

        public function render(): Column
        {
            return Column::make();
        }
    };

    $id = NativeEventHandlers::register('tool:set', function (array $payload, NativeComponent $target): void {
        $target->value = (int) ($payload['value'] ?? 0);
    });

    expect(NativeEventHandlers::dispatch('tool:set', ['value' => 42], $component))->toBeTrue()
        ->and($component->value)->toBe(42)
        ->and(NativeEventHandlers::dispatch('tool:missing', [], $component))->toBeFalse();

    NativeEventHandlers::unregister($id);

    expect(NativeEventHandlers::dispatch('tool:set', ['value' => 9], $component))->toBeFalse();
});

it('requires package event names to be namespaced', function () {
    NativeEventHandlers::register('unscoped', static fn () => null);
})->throws(InvalidArgumentException::class);

it('observes interaction dispatch around the actual component mutation', function () {
    $observer = runtimeObserverSpy();
    RuntimeObservers::register($observer);
    $component = new RuntimeObservedComponent;
    $callbackId = $component->initializeCallbacks();

    $component->dispatchUi(['type' => 1, 'callback_id' => $callbackId, 'node_id' => 7]);

    expect($component->count)->toBe(1)
        ->and($observer->starting)->toHaveCount(1)
        ->and($observer->starting[0]['kind'])->toBe('interaction')
        ->and($observer->starting[0]['before']['count'])->toBe(0)
        ->and($observer->finished)->toHaveCount(1)
        ->and($observer->finished[0]['after']['count'])->toBe(1)
        ->and($observer->finished[0]['nodeId'])->toBe(7)
        ->and($observer->finished[0]['durationMs'])->toBeFloat();
});

it('routes package native events through the component and observes them', function () {
    $observer = runtimeObserverSpy();
    RuntimeObservers::register($observer);
    $component = new RuntimeObservedComponent;
    $component->initializeCallbacks();
    NativeEventHandlers::register('tool:set', function (array $payload, NativeComponent $target): void {
        $target->count = (int) $payload['value'];
    });

    $component->dispatchPluginEvent('tool:set', ['value' => 13]);

    expect($component->count)->toBe(13)
        ->and($observer->starting[0]['kind'])->toBe('native')
        ->and($observer->finished[0]['after']['count'])->toBe(13);
});
