<?php

declare(strict_types=1);

namespace App\Profile\Target;

use App\Profile\AbstractTarget;

class AzureBlobTarget extends AbstractTarget
{
    public function __construct(
        public readonly string $dsn,
        public readonly string $container,
        public readonly string $path
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['dsn'],
            $data['container'],
            $data['path']
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Target\AzureBlob::class;
    }
}