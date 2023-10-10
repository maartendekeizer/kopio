<?php

declare(strict_types=1);

namespace App\Source;

use App\Helper\Logger;
use App\Profile\AbstractSource;

interface SourceInterface
{
    public function __construct(
        AbstractSource $config,
        string $tmp,
        string $runId
    );

    public function execute(Logger $logger): array;
}