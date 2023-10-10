<?php

declare(strict_types=1);

namespace App\Profile;

interface ExecutorConfigurationInterface
{
    public function getExecutorClass(): string;

    public function getExecutorCalls(): array;
}