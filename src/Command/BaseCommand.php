<?php

declare(strict_types=1);
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use App\Backup\AbstractBackup;
use App\Backup\MySqlBackup;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use DateTime;
use InvalidArgumentException;
use App\Exception\UnknownSourceException;


use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FilesystemException;


#[AsCommand(
    name: 'Base',
    description: 'Add a short description for your command',
)]

abstract class BaseCommand extends Command
{
    protected $logger;
//    protected $mailer;

    protected $timeStart;
    protected $timeEnd;
    protected $jobType;

    private FilesystemOperator $kopioStorage;



    public function __construct(LoggerInterface $logger, FilesystemOperator $kopioStorage)
    {
        parent::__construct();

        $this->logger= $logger;
        $this->timeStart = $timeEnd = null;
        $this->jobType = null;   
        
        $this->kopioStorage = $kopioStorage;

    }

    protected function configure(): void
    {
        $this
            ->addArgument('profilesDirectory', InputArgument::REQUIRED, '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profilesMap = $input->getArgument('profilesDirectory');

        if ($profilesMap) {
            $io->note(sprintf('You passed an argument: %s', $profilesMap));
        }

        try 
        {
            $listing = $this->kopioStorage->listContents('\\' . $profilesMap, FALSE);

            foreach ($listing as $item) {
                $path = $item->path();

                if ($item instanceof \League\Flysystem\FileAttributes) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);            
                    if ($ext == 'yaml' || $ext == 'yml' )
                        {
                            $files[] = $path;
                        }
                } elseif ($item instanceof \League\Flysystem\DirectoryAttributes) {
                    // handle the directory
                }
            }
        } catch (FilesystemException $exception) {
            // handle the error
        }

        $jobsCounter = 0;
        $jobsFailed = [];
        $jobsSuccess= [];

        $this->timeStart = new DateTime();
        foreach ($files as $file) {
            $jobsCounter ++;

            try 
            {
                $parsedFile = Yaml::parse($this->kopioStorage->read($file));
            } catch (FilesystemException $exception) {
                $io->error($exception->getMessage()); 
                throw new UnknownSourceException();
            }   

            $io->note('Parsing: ' . $file);

            $backupJob = null;

            switch (TRUE)
            {
                case isset($parsedFile['mariadb']):
                    $io->note('Starting action for MySQL/MariaDB with profile: ' . $parsedFile['mariadb']['name']);
                    $backupJob = new MySqlBackup($parsedFile['mariadb']['name'], 'Backup', $parsedFile['mariadb']['source'], $parsedFile['mariadb']['target']['filesystem'] . DIRECTORY_SEPARATOR . $parsedFile['mariadb']['name'], strval($parsedFile['mariadb']['retention']['simple']['days']));
                    break;
                default:
                    $io->error('Unknown backup type'); 
                    throw new InvalidArgumentException('Unknown backup type');
            }

/*
            if (isset($parsedFile['mariadb'])) 
            {
                $io->note('Starting action for MySQL/MariaDB with profile: ' . $parsedFile['mariadb']['name']);
                $backupJob = new MySqlBackup($parsedFile['mariadb']['name'], 'Backup', $parsedFile['mariadb']['source'], $parsedFile['mariadb']['target']['filesystem'] . DIRECTORY_SEPARATOR . $parsedFile['mariadb']['name'], strval($parsedFile['mariadb']['retention']['simple']['days']));

            } else if (isset($parsedFile['postgresql'])) {
                $io->note('Starting action for postreSql with profile: ' . $parsedFile['postgresql']['name']);
                $backupJob = new PostgreSqlBackup($parsedFile['postgresql']['name'], 'Backup', $parsedFile['postgresql']['source'], $parsedFile['postgresql']['target']['filesystem'] . DIRECTORY_SEPARATOR . $parsedFile['postgresql']['name'], strval($parsedFile['postgresql']['retention']['simple']['days']));
                $messageSender = $this->messageSenderFactory->createMessageSender($parsedFile['postgresql']['notifications'], $backupJob);
            } else if (isset($parsedFile['local'])) {
                $io->note('Starting action for local backup with profile: ' . $parsedFile['local']['name']);
                $backupJob = new LocalBackup($parsedFile['local']['name'], 'Backup',$parsedFile['local']['source'], $parsedFile['local']['destination']['filesystem'] . DIRECTORY_SEPARATOR . $parsedFile['local']['name'], strval($parsedFile['local']['retention']['simple']['days']));
                $messageSender = $this->messageSenderFactory->createMessageSender($parsedFile['local']['notifications'], $backupJob);
            } else if (isset($parsedFile['scp'])) {
                $io->note('Starting action for scp backup with profile: ' . $parsedFile['scp']['name']);
                $backupJob = new SCPBackup($parsedFile['scp']['name'], 'Backup', $parsedFile['scp']['source'], $parsedFile['scp']['destination']['filesystem'] . DIRECTORY_SEPARATOR . $parsedFile['scp']['name'], strval($parsedFile['scp']['retention']['simple']['days']));
                $messageSender = $this->messageSenderFactory->createMessageSender($parsedFile['scp']['notifications'], $backupJob);

            } else {
                $io->error('Unknown backup type'); 
                throw new InvalidArgumentException('Unknown backup type');
            }
*/
// marien            $this->jobType = $backupJob->getType();

            if (!$this->doExecute($backupJob, $io, $this->logger)) {
                $jobsFailed[] = $backupJob;
            } else {
                $jobsSuccess[] = $backupJob;
            }

            sleep($_ENV['SLEEPTIME']);
        }    
    
        $this->timeEnd = new DateTime();

        // reporting
        foreach ($jobsSuccess as $job) {
        //  $message .= PHP_EOL . $job->getName() . PHP_EOL; 
        }

        if (!empty($jobsFailed)) {
            $io->error('Failed to create ' . count($jobsFailed) . ' of ' . $jobsCounter .' backups:');
            
            $count = 1;
            foreach ($jobsFailed as $job) {
                $io->error($count . ' [' . $job->getType() . '] ' . $job->getName() . ' failed with exception: ' . $job->getException()->getMessage());
                $count ++;
            }
        
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    } 
    
    protected abstract function doExecute(
            AbstractBackup $backupJob, 
            SymfonyStyle $io,
            LoggerInterface $logger
        ): bool;

    
}
