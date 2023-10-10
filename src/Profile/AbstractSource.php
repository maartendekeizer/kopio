<?php

declare(strict_types=1);

namespace App\Profile;

abstract class AbstractSource implements ExecutorConfigurationInterface
{
    abstract public function getExecutorClass(): string;

    public function getExecutorCalls(): array
    {
        return [];
    }

    abstract public static function fromArray(array $data): self;
}