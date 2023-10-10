<?php

declare(strict_types=1);

namespace App\Profile\Source;

use App\Profile\AbstractSource;

class MongoDbSource extends AbstractSource
{
    public function __construct(
        public readonly string $uri,
        public readonly string $executable
    )
    {
    }

    public static function fromArray(array $data): self
    {
        // defaults
        if (empty($data['executable'])) {
            $data['executable'] = 'mongodump';
        }

        return new self(
            $data['uri'],
            $data['executable']
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Source\MongoDb::class;
    }
}