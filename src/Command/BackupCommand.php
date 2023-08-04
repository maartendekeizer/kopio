<?php

declare(strict_types=1);
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
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
            $backupStatus = $backupJob->executeBackup();
            $this->addMonitor('backupMessage',  $backupStatus); 
          
            if (!$backupStatus || $backupStatus === 'success' ) {
                $this->addMonitor('file_created',  'Backup file created'); 
                return true;
            } else {    
                $this->addMonitor('failed',  $backupStatus, 'ERROR'); 
                return false;                
            }    
        } catch (\Exception $exception) {
            $this->addMonitor('failed',  $exception->getMessage(), 'ERROR' );  
            return false;
        }
    } 
}
