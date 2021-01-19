<?php

declare(strict_types=1);
namespace App;

class MessageSenderFactory
{
    protected $mailTransport;
    protected $mailSender;

    public function __construct(\Symfony\Component\Mailer\Transport\TransportInterface $mailTransport, string $mailSender)
    {
        $this->mailSender = $mailSender;
        $this->mailTransport = $mailTransport;
    }

    public function createMessageSender(?array $config, $backupJob): MessageSender
    {
        if ($config === null) {
            $config = [];
        }

        return new MessageSender($config, $this->mailTransport, $backupJob, $this->mailSender);
    }
}