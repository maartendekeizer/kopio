<?php

declare(strict_types=1);

namespace App\Retention;

use App\Profile\AbstractRetention;
use App\Profile\Retention\SimpleRetention;
use InvalidArgumentException;

class Simple implements RetentionInterface
{
    private SimpleRetention $config;

    public function __construct(
        AbstractRetention $config,
        private string $runId
    )
    {
        if (($config instanceof SimpleRetention) === false) {
            throw new InvalidArgumentException('Config should be instance of SimpleNotification');
        }
        $this->config = $config;
    }

    public function nominate(array $backups): array
    {
        // remove all without a valid commandStart property
        $backups = array_filter($backups, function (array $meta): bool {
            return isset($meta['commandStart']);
        });

        // we only need the backup date, strip all other data
        $backups = array_map(function (array $meta): int {
            return strtotime($meta['commandStart']);
        }, $backups);

        // sort on date
        asort($backups);

        // do nothing when we have not enough backups
        if (count($backups) <= ($this->config->count + 1)) {
            return [];
        }

        // remove old backups from list
        return array_keys(array_slice($backups, 0, count($backups) - ($this->config->count)));
    }
}