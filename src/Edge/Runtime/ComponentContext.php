<?php

namespace Native\Mobile\Edge\Runtime;

use Native\Mobile\Edge\NativeComponent;

final readonly class ComponentContext
{
    public function __construct(
        public NativeComponent $component,
        public string $uri,
        public int $renderCount,
    ) {}
}
