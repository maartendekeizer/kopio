<?php

declare(strict_types=1);

namespace App\Profile\Notification;

use App\Profile\AbstractNotification;

class EmailNotification extends AbstractNotification
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $on
    )
    {
    }

    public static function fromArray(string $on, array $data): self
    {
        return new self(
            $data['from'],
            $data['to'],
            $data['subject'],
            $on
        );
    }

    public function getExecutorClass(): string
    {
        return \App\Notification\Email::class;
    }

    public function getExecutorCalls(): array
    {
        return ['setMailer' => 'public_mailer'];
    }
}