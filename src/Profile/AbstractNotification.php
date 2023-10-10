<?php

declare(strict_types=1);

namespace App\Profile;

abstract class AbstractNotification implements ExecutorConfigurationInterface
{
    const ON_SUCCESS = 'success';
    const ON_FAILURE = 'failure';

    public readonly string $on;

    abstract public function getExecutorClass(): string;

    abstract public function getExecutorCalls(): array;
}