<?php

declare(strict_types=1);

namespace App\Profile;

use InvalidArgumentException;

abstract class SourceFactory
{
    public static function fromArray(array $data): AbstractSource
    {
        if (count($data) !== 1) {
            throw new InvalidArgumentException('Source should be have one key');
        }

        if (isset($data['mysql']) === true) {
            return Source\MySqlSource::fromArray($data['mysql']);
        } elseif (isset($data['postgresql']) === true) {
            return Source\PostgreSqlSource::fromArray($data['postgresql']);
        } elseif (isset($data['filesystem']) === true) {
            return Source\FilesystemSource::fromArray($data['filesystem']);
        } elseif (isset($data['sftp']) === true) {
            return Source\SftpSource::fromArray($data['sftp']);
        } elseif (isset($data['mongodb']) === true) {
            return Source\MongoDbSource::fromArray($data['mongodb']);
        }

        reset($data);
        throw new InvalidArgumentException('Unknow key in source definition ' . key($data));
    }
}