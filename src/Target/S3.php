<?php

declare(strict_types=1);

namespace App\Target;

use App\Profile\AbstractTarget;
use App\Profile\Target\S3Target;
use Aws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;

class S3 extends AbstractFlysystem
{
    private S3Target $config;

    public function __construct(
        AbstractTarget $config,
        protected string $runId
    )
    {
        if (($config instanceof S3Target) === false) {
            throw new InvalidArgumentException('Config should be instance of S3Target');
        }
        $this->config = $config;
    }

    protected function getOrCreateFilesystem(): Filesystem
    {
        if ($this->fs !== null) {
            return $this->fs;
        }

        $client = new S3Client([
            'region' => $this->config->region,
            'version' => $this->config->version,
            'endpoint' => $this->config->endpoint,
            'credentials' => [
                'key' => $this->config->accessKey,
                'secret' => $this->config->secret
            ],
        ]);

        $adapter = new AwsS3V3Adapter(
            $client,
            $this->config->bucket,
            $this->config->path,
            new PortableVisibilityConverter(Visibility::PRIVATE)
        );

        return $this->fs = new Filesystem($adapter);
    }
}