<?php

declare(strict_types=1);

namespace App\Profile\Source;

use App\Profile\AbstractSource;

class FilesystemSource extends AbstractSource
{
    public function __construct(
        public readonly array $locations,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        $locations = [];
        foreach ($data['locations'] as $location) {
            $locations[] = FilesystemSource\Location::fromArray($location);
        }
        return new self($locations);
    }

    public function getExecutorClass(): string
    {
        return \App\Source\Filesystem::class;
    }
}