<?php

declare(strict_types=1);

namespace App\Profile\Retention;

use App\Profile\AbstractRetention;

class SimpleRetention extends AbstractRetention
{
    public function __construct(
        public readonly int $count
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['count'],
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Retention\Simple::class;
    }
}