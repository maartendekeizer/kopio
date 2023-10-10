<?php

declare(strict_types=1);

namespace App\Profile\Target;

use App\Profile\AbstractTarget;

class S3Target extends AbstractTarget
{
    public function __construct(
        public readonly string $region,
        public readonly string $version,
        public readonly string $endpoint,
        public readonly string $accessKey,
        public readonly string $secret,
        public readonly string $bucket,
        public readonly string $path
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['region'],
            $data['version'],
            $data['endpoint'],
            $data['accessKey'],
            $data['secret'],
            $data['bucket'],
            $data['path']
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Target\S3::class;
    }
}