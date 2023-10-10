<?php

declare(strict_types=1);

namespace App\Profile\Target;

use App\Profile\AbstractTarget;

class FilesystemTarget extends AbstractTarget
{
    public function __construct(
        public readonly string $path
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['path']);
    }

    public function getExecutorClass(): string
    {
        return \App\Target\Filesystem::class;
    }
}