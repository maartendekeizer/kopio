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
            $logger->info('Creating backup', ['profileName' => $backupJob->getName()]);
            $io->note('Checking and creating destination directories for the backup');
            $backupJob->prepareBackup();
            
            $io->note('Checking if all information is defined in de yaml profiles file');
            $backupJob->verifyConfig();

/*           
            if (!isset($parsedFile['scp'])) {
                $output->writeln('Testing connecting source');
                $backupJob->checkSource();
            }
*/
            $io->note('Creating the backup');
            $backupJob->executeBackup();

            $io->success('Finished backup');

/*
            try {
                $messageSender->sendSuccess();
            } catch (\Exception $e) {
                $io->error('Failed sending notification with exception: '  . get_class($e) . ' and message ' . $e->getMessage(), ['profileName' =>  $backupJob->getName()]);                
                $logger->error('ERROR: Failed sending notification with exception: '  . get_class($e) . ' and message ' . $e->getMessage(), ['profileName' =>  $backupJob->getName()]);
            }
*/
            $logger->info('Succesfully created backup:', ['profileName' =>  $backupJob->getName()]);
            $io->success('Succesfully created backup:', ['profileName' =>  $backupJob->getName()]);
            return true;

        } catch (\Exception $e) {
            $io->error('ERROR: there is an exception: '  . get_class($e) . ' with message ' . $e->getMessage());
            $backupJob->setException($e);
            $logger->error('ERROR: Failed creating backup with exception: '  . get_class($e) . ' and message ' . $e->getMessage(), ['profileName' =>  $backupJob->getName()]);

            try {
//                $messageSender->sendFailure();
            } catch (\Exception $e) {
                $logger->error('ERROR: Failed sending notification with exception: '  . get_class($e) . ' and message ' . $e->getMessage(), ['profileName' =>  $backupJob->getName()]);
            }

            return false;
        }
    } 
        

}
