<?php

declare(strict_types=1);
namespace App;

use InvalidArgumentException;
use Symfony\Component\Notifier\Channel\ChatChannel;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Channel\EmailChannel;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransport;
use Symfony\Component\HttpClient\HttpClient;
use App\Backup\AbstractBackup;

class MessageSender
{
    protected $configuration;
    private $mailTransport;
    protected $backupJob;
    protected $mailSender;

    public function __construct(array $configuration, \Symfony\Component\Mailer\Transport\TransportInterface $mailTransport, AbstractBackup $backupJob, string $mailSender)
    {
        $this->configuration = $configuration;
        $this->mailTransport = $mailTransport;
        $this->backupJob = $backupJob;
        $this->mailSender = $mailSender;
    }

    public function sendSuccess(): void
    {
        foreach ($this->configuration as $config) {
            if ($config['when'] === 'succeed' || $config['when'] === 'always') {
                $this->send($config, 'succeed', $this->backupJob->getName(), $this->backupJob->getType(), $this->backupJob->getException());
            }
        }
    }

    public function sendFailure(): void
    {
        foreach ($this->configuration as $config) {
            if ($config['when'] === 'failure' || $config['when'] === 'always') {
                $this->send($config, 'failure', $this->backupJob->getName(), $this->backupJob->getType(), $this->backupJob->getException());
            }
        }
    }

    protected function send(array $config, string $status, string $backupJobName, string $backupJobType, \Exception $backupJobException = null): void
    {
        if (array_key_exists('email', $config)){
            $this->sendEmail($config, $status, $backupJobName, $backupJobType, $backupJobException);
        } else if (array_key_exists('teams', $config)) {
            $this->sendTeamsMessage($config, $status, $backupJobName, $backupJobType, $backupJobException);
        } else if (array_key_exists('webhook', $config)) {
            $this->sendWebhookMessage($config, $status, $backupJobName, $backupJobType, $backupJobException);
        } else {
             new InvalidArgumentException('Send method does not exist');
        }
    }

    protected function sendEmail(array $config, string $status, string $backupJobName, string $backupJobType, \Exception $backupJobException = null) 
    {
        $channel = new EmailChannel($this->mailTransport, null, $this->mailSender);
        $notifier = new Notifier(['email' => $channel]);

        if ($status === 'failed') {
            $notification = new Notification(str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}", "{EXCEPTION}"], [$status, $backupJobName, $backupJobType, $backupJobException->getMessage()], $config['email']['description']), ['email']);
        } else {
            $notification = new Notification(str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}"], [$status, $backupJobName, $backupJobType], $config['email']['description']), ['email']);
        }

        $notification->importance('high');
        
        $notifier->send($notification, new Recipient($config['email']['to']));
    }

    protected function sendTeamsMessage(array $config, string $status, string $backupJobName, string $backupJobType, \Exception $backupJobException = null) 
    {
        $microsoftTransport = new MicrosoftTeamsTransport($config['teams']['DSN']);
        
        $channel = new ChatChannel($microsoftTransport);
        $notifier = new Notifier(['chat' => $channel]);
       
        if ($status === 'failed') {
            $notification = new Notification(str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}", "{EXCEPTION}"], [$status, $backupJobName, $backupJobType, $backupJobException->getMessage()], $config['teams']['description']), ['chat']);
        } else {
            $notification = new Notification(str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}"], [$status, $backupJobName, $backupJobType], $config['teams']['description']), ['chat']);
        }
        
        $notifier->send($notification);
    }

    protected function sendWebhookMessage(array $config, string $status, string $backupJobName, string $backupJobType, \Exception $backupJobException = null) 
    {
        $client = HttpClient::create();
        if ($config['webhook']['method'] === "POST")  {
            if ($status === 'failed') {
                $client->request("POST", $config['webhook']['url'], ['json' =>  ['text' => str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}", "{EXCEPTION}"], [$status, $backupJobName, $backupJobType, $backupJobException->getMessage()], $config['webhook']['message'])]]);
            } else {
                $client->request("POST", $config['webhook']['url'], ['json' =>  ['text' => str_replace(["{STATUS}", "{JOBNAME}", "{JOBTYPE}"], [$status, $backupJobName, $backupJobType], $config['webhook']['message'])]]);
            }
        } else if ($config['webhook']['method'] === "GET") {
            $client->request("GET", $config['webhook']['url']);
        }
    }
}
