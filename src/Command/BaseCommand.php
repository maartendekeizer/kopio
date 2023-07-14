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
use App\Backup\PostgresBackup;


use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

use DateTime;
use InvalidArgumentException;
use App\Exception\UnknownSourceException;
use App\Exception\UnknownTargetException;
use App\Exception\ConnectionErrorException;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FilesystemException;

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

#[AsCommand(
    name: 'Base',
    description: 'Add a short description for your command',
)]

abstract class BaseCommand extends Command
{
    protected $logger;
//    protected $mailer;

    protected $jobType;

    private FilesystemOperator $kopioStorage;

    protected $profileStorage;

    protected $workingDir;
    protected $jobMonitor;
    protected $yamlInput;
    protected $io;

    public function __construct(LoggerInterface $logger, FilesystemOperator $kopioStorage)
    {
        parent::__construct();

        $this->logger= $logger;
        $this->jobType = null;   
        $this->kopioStorage = $kopioStorage;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('profileDirectory', InputArgument::REQUIRED, '')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $profileDirectory = $input->getArgument('profileDirectory');

        if ($profileDirectory) {
            $this->io->note(sprintf('You passed an argument: %s', $profileDirectory));
        } else {
            $this->addMonitor('failed', 'No commandline argument' );              
            $this->jobNotification('No commandline argument');              
            throw new FileSystemException();
        }

        $this->createProfileStorage($profileDirectory);    
        $workingStorage = $this->createWorkingStorage($profileDirectory);   

        try 
        {
            $listing = $this->profileStorage->listContents('\\', FALSE);
            foreach ($listing as $item) {
                $path = $item->path();

                if ($item instanceof \League\Flysystem\FileAttributes) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);            
                    if ($ext == 'yaml' || $ext == 'yml' )
                        {
                            $files[] = $path;
                        }
                } 
            }
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', 'Profiles ' . $exception->getMessage() );  
            $this->jobNotification($exception->getMessage());              
            throw new FileSystemException($exception->getMessage());
        }

        foreach ($files as $file) {
            try 
            {
                $this->yamlInput = Yaml::parse($this->profileStorage->read($file));
            } catch (FilesystemException $exception) {
                $this->addMonitor('failed', 'Profile ' . $file . ' ' . $exception->getMessage());  
                $this->jobNotification($exception->getMessage());                  
                throw new UnknownSourceException($exception->getMessage());
            }   

            $this->io->note('Parsing: ' . $file);
            $this->addMonitor('profile', $this->yamlInput['name']);  
            $this->addMonitor('profileFile', $file);  

            $this->addMonitor('notificationWhen', $this->yamlInput['notifications']['when']);  
            $this->addMonitor('notificationEmailTo', $this->yamlInput['notifications']['email']);  
            $this->addMonitor('notificationSubject', $this->yamlInput['notifications']['subject']);  


            $backupJob = null;
            $targetStorage = $this->createTargetStorage();

            switch (TRUE)
            {
                case isset($this->yamlInput['source']['mariadb']):
                    $this->addMonitor('mariadb', $this->yamlInput['source']['mariadb']['database']);  
                    $keysToCheck = ['username', 'password', 'host', 'database'];
                    $this->verifyConfig($keysToCheck, $this->yamlInput['source']['mariadb']);
                    $backupJob = new MySqlBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage);
                    break;
                case isset($this->yamlInput['source']['postgres']):
                    $this->addMonitor('mariadb', $this->yamlInput['source']['postgres']['database']);  
                    $keysToCheck = ['username', 'password', 'host', 'database'];
                    $this->verifyConfig($keysToCheck, $this->yamlInput['source']['postgres']);
                    $backupJob = new PostgresBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage);
                    break;                    
                default:
                    $this->addMonitor('failed', 'Unknown source type' . $this->yamlInput['source'] );  
                    $this->jobNotification('Unknown source type' . $this->yamlInput['source']);
                    throw new UnknownSourceException('Unknown source type');
            }

            $this->io->note('Starting action with profile: ' . $this->yamlInput['name']);

            $this->addMonitor('begin', new DateTime() );  
            if (!$this->doExecute($backupJob, $this->io, $this->logger)) {
                $this->addMonitor('failed', 'Unknown (doExecute)' . $this->yamlInput['source'] );  
                $this->jobNotification('Unknown (doExecute)');
            }
            $this->addMonitor('end', new DateTime() );  

            $this->io->note('Sleep');
            sleep(intval($_ENV['SLEEPTIME']));
        }    

        $status = $this->jobNotification();       
        if ($status) {
            return Command::SUCCESS;
        }
        $this->io->error('Job ended with errors');
        return Command::FAILURE;
    }

    protected function outputMonitor2jobMonitor($outputMonitor): void
    {
        foreach ($outputMonitor as $key => $value){
            $this->addMonitor($key, $value);
        }
    }    

    protected function addMonitor($item, $message): void
    {
        if (!isset($this->yamlInput['name'])) {
            $this->yamlInput['name'] = 'General';
        }
        $this->jobMonitor[$this->yamlInput['name']] [$item] = $message;  
    }

    protected function createProfileStorage($profileDirectory): void
    {    
        try
        {
            $adapter = new LocalFilesystemAdapter($profileDirectory);
            $this->profileStorage = new Filesystem($adapter);
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', 'Profiles directory: ' . $profileDirectory . ' ' . $exception->getMessage());  
            $this->jobNotification($exception->getMessage());              
            throw new FileSystemException();
        } 
    }
    
    protected function createWorkingStorage($profileDirectory ): Filesystem
    {
        $this->workingDir = $profileDirectory . DIRECTORY_SEPARATOR . 'temp';
        $this->addMonitor('WorkingDirectory', $this->workingDir);
        try
        {
            $adapter = new LocalFilesystemAdapter($this->workingDir);
            $workingStorage = new Filesystem($adapter);
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', 'Profiles directory: ' . $this->workingDir . ' ' . $exception->getMessage());  
            $this->jobNotification($exception->getMessage());              
            throw new FileSystemException();
        } 

        return $workingStorage;
    }

    protected function createTargetStorage(): Filesystem
    {
        try
        {
            switch (TRUE)
            {
                case isset($this->yamlInput['target']['filesystem']):
                    $this->addMonitor('targetType' , 'filesystem');  
                    $this->addMonitor('target' , $this->yamlInput['target']['filesystem']);  
                    $adapter = new LocalFilesystemAdapter($this->yamlInput['target']['filesystem']);
                    $keysToCheck = ['filesystem'];
                    $this->verifyConfig($keysToCheck, $this->yamlInput['target']);
                    $targetStorage = new Filesystem($adapter);
// TODO add other destinations                        
                    break;
                default:
                    $this->addMonitor('failed', 'Unknown target filesystem');  
                    $this->jobNotification('Unknown target filesystem');           
                    throw new UnknownTargetException();
            }  
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', $exception->getMessage());  
            $this->jobNotification($exception->getMessage());  
            throw new FileSystemException($exception->getMessage());
        }

        return $targetStorage;                      
    }

    public function verifyConfig($keysToCheck, $array): void 
    {
        foreach($keysToCheck as $key) 
        {
            if (!array_key_exists($key, $array)) {
                $this->addMonitor('failed', 'No ' . $key . ' defined');  
                $this->jobNotification('No ' . $key . ' defined');  
                throw new InvalidArgumentException('No ' . $key . ' defined');
            }    
        }
    } 
  
    protected abstract function doExecute(
            AbstractBackup $backupJob, 
            SymfonyStyle $io,
            LoggerInterface $logger
            ): bool;

    protected function jobNotification($errorMessage = NULL): bool 
    {
        $status = true;
        if (!empty($errorMessage)) {
            $this->io->error($errorMessage);
            $this->logger->error($errorMessage);
            $status = false;
            print_r($this->jobMonitor); 
        }
     
        foreach ($this->jobMonitor as $job)
        {
            if (isset($job['failed'] ) )
            {
                $status = false;
                print_r($this->jobMonitor);
            }
        }

    print_r($this->jobMonitor);        

/* TODO
     SEND MAIL

             // reporting
        $message = 'Successfully ended jobs: ';
        foreach ($jobsSuccess as $job) 
        {
              $message .= PHP_EOL . $job . PHP_EOL; 
        }
        echo $message;
*/  
        return $status;

    }        
    
}
