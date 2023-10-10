<?php

declare(strict_types=1);

namespace App\Target;

use App\Helper\Logger;
use App\Profile\AbstractTarget;

interface TargetInterface
{
    public function __construct(
        AbstractTarget $config,
        string $runId
    );

    public function copy(Logger $logger, string $tmpPath, array $files): void;

    public function writeMetaData(Logger $logger, string $metaData): void;

    public function list(Logger $logger): array;

    public function remove(Logger $logger, array $backupsToRemove): void;
}