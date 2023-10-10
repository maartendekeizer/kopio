<?php

declare(strict_types=1);

namespace App\Target;

use App\Profile\AbstractTarget;
use App\Profile\Target\SftpTarget;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

class Sftp extends AbstractFlysystem
{
    private SftpTarget $config;

    public function __construct(
        AbstractTarget $config,
        protected string $runId
    )
    {
        if (($config instanceof SftpTarget) === false) {
            throw new InvalidArgumentException('Config should be instance of SftpTarget');
        }
        $this->config = $config;
    }

    protected function getOrCreateFilesystem(): Filesystem
    {
        if ($this->fs !== null) {
            return $this->fs;
        }

        $provider = new SftpConnectionProvider(
            $this->config->host,
            $this->config->username,
            $this->config->password,
            $this->config->pathPrivateKey,
            $this->config->passphrasePrivateKey,
            $this->config->port,
            $this->config->useAgent,
            hostFingerprint: $this->config->fingerprint
        );

        $adapter = new SftpAdapter(
            $provider,
            $this->config->path,
            null
        );

        return $this->fs = new Filesystem($adapter);
    }
}