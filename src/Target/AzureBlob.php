<?php

declare(strict_types=1);

namespace App\Target;

use App\Profile\AbstractTarget;
use App\Profile\Target\AzureBlobTarget;
use InvalidArgumentException;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;
use League\Flysystem\Filesystem;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class AzureBlob extends AbstractFlysystem
{
    private AzureBlobTarget $config;

    public function __construct(
        AbstractTarget $config,
        protected string $runId
    )
    {
        if (($config instanceof AzureBlobTarget) === false) {
            throw new InvalidArgumentException('Config should be instance of AzureBlobTarget');
        }
        $this->config = $config;
    }

    protected function getOrCreateFilesystem(): Filesystem
    {
        if ($this->fs !== null) {
            return $this->fs;
        }

        $client = BlobRestProxy::createBlobService($this->config->dsn);

        $adapter = new AzureBlobStorageAdapter(
            $client,
            $this->config->container,
            $this->config->path
        );

        return $this->fs = new Filesystem($adapter);
    }
}