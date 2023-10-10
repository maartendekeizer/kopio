<?php

declare(strict_types=1);

namespace App\Notification;

use App\Helper\Logger;
use App\Profile\AbstractNotification;

interface NotificationInterface
{
    public function __construct(
        AbstractNotification $config,
        string $profileName,
        string $runId
    );

    public function execute(Logger $logger, string $command, array $data): void;
}