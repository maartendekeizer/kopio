<?php

declare(strict_types=1);

namespace App\Retention;

interface RetentionInterface
{
    /**
     * @param array $backups Is an array of the backup key/path as key and meta data as value
     * @return array Array of backup key/paths that can be deleted
     */
    public function nominate(array $backups): array;
}