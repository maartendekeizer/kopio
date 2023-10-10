<?php

declare(strict_types=1);

namespace App\Notification;

use App\Helper\Logger;
use App\Profile\AbstractNotification;
use App\Profile\Notification\EmailNotification;
use InvalidArgumentException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as MimeEmail;

class Email implements NotificationInterface
{
    private EmailNotification $config;

    private MailerInterface $mailer;

    public function __construct(
        AbstractNotification $config,
        private string $profileName,
        private string $runId
    )
    {
        if (($config instanceof EmailNotification) === false) {
            throw new InvalidArgumentException('Config should be instance of EmailNotification');
        }
        $this->config = $config;
    }

    public function setMailer(MailerInterface $mailer): void
    {
        $this->mailer = $mailer;
    }

    public function execute(Logger $logger, string $command, array $data): void
    {
        $body = '';
        $body .= 'Run ID: ' . $this->runId . PHP_EOL;
        $body .= 'Command: ' . $command . PHP_EOL;
        $body .= 'State: ' . $this->config->on . PHP_EOL;
        $body .= '' . PHP_EOL;
        foreach ($data as $k => $v) {
            $body .= $k .': ' . (is_scalar($v) ? $v : json_encode($v)) . PHP_EOL;
        }

        $message = new MimeEmail();
        $message->to($this->config->to);
        $message->from($this->config->from);
        $message->subject(str_replace(['%command%', '%name%', '%runId%'], [$command, $this->profileName, $this->runId], $this->config->subject));
        $message->text($body);
        if ($this->config->on === AbstractNotification::ON_SUCCESS) {
            $message->attach(json_encode($data), 'metadata.json', 'application/json');
        }
        $this->mailer->send($message);
    }
}