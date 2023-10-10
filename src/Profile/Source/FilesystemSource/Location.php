<?php

declare(strict_types=1);

namespace App\Profile\Source\FilesystemSource;

class Location
{
    public function __construct(
        public readonly string $name,
        public readonly string $path,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['path']);
    }
}