<?php

declare(strict_types=1);

namespace App\Profile\Source;

use App\Profile\AbstractSource;

class SftpSource extends AbstractSource
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $path,
        public readonly string $username,
        public readonly ?string $password = null,
        public readonly ?string $pathPrivateKey = null,
        public readonly ?string $passphrasePrivateKey = null,
        public readonly bool $useAgent = true,
        public readonly ?string $fingerprint = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        // defaults
        $data = array_merge([
            'port' => 22,
            'password' => null,
            'pathPrivateKey' => null,
            'passphrasePrivateKey' => null,
            'useAgent' => true,
            'fingerprint' => null,
        ], $data);

        return new self(
            $data['host'],
            $data['port'],
            $data['path'],
            $data['username'],
            $data['password'],
            $data['pathPrivateKey'],
            $data['passphrasePrivateKey'],
            $data['useAgent'],
            $data['fingerprint'],
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Source\Sftp::class;
    }
}