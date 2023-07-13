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

use App\Exception\BackupFailedException;

use App\Backup\AbstractBackup;

use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:create-backup',
    description: 'Add a short description for your command',
)]

class BackupCommand extends BaseCommand
{
    protected static $defaultName = 'app:create-backup';

    protected function doExecute(
            AbstractBackup $backupJob, 
            SymfonyStyle $io,
            LoggerInterface $logger
        ): bool
    {
        try {
            $io->note('Creating the backup');
            $outputMonitor = $backupJob->executeBackup();
            $this->outputMonitor2jobMonitor($outputMonitor);
   
            if (isset($outputMonitor['errorMessage']) ) {
                $this->addMonitor('failed',  $outputMonitor['errorMessage']); 
                $this->jobNotification($outputMonitor['errorMessage']);              
                throw new BackupFailedException($outputMonitor['errorMessage']);
                return false;                
            } else { 
                $this->addMonitor('success',  'Succesfully created backup: ' .  $this->yamlInput['name']); 
                $logger->info('Succesfully created backup: ', ['profileName' => $this->yamlInput['name']]);
                $io->success('Succesfully created backup: ', ['profileName' =>  $this->yamlInput['name']]);
                return true;
            }
        } catch (\Exception $exception) {
            $this->addMonitor('failed',  $exception->getMessage() );  
            $this->jobNotification($exception->getMessage());              
            return false;
        }
    } 
}
