<?php

declare(strict_types=1);

namespace App\Profile;

use InvalidArgumentException;

abstract class TargetFactory
{
    public static function fromArray(array $data): AbstractTarget
    {
        if (count($data) !== 1) {
            throw new InvalidArgumentException('Target should be have one key');
        }

        if (isset($data['filesystem']) === true) {
            return Target\FilesystemTarget::fromArray($data['filesystem']);
        } elseif (isset($data['s3']) === true) {
            return Target\S3Target::fromArray($data['s3']);
        } elseif (isset($data['azureBlob']) === true) {
            return Target\AzureBlobTarget::fromArray($data['azureBlob']);
        } elseif (isset($data['sftp']) === true) {
            return Target\SftpTarget::fromArray($data['sftp']);
        }

        reset($data);
        throw new InvalidArgumentException('Unknow key in target definition ' . key($data));
    }
}