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
use App\Backup\FilesystemBackup;
use App\Backup\SFTPBackup;

use Psr\Log\LoggerInterface;
use Exception;

use DateTime;
use InvalidArgumentException;
use App\Exception\UnknownSourceException;
use App\Exception\UnknownTargetException;
use App\Exception\ConnectionErrorException;

use Symfony\Component\Yaml\Yaml;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FilesystemException;

use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Adapter\Local;

use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter; 
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsPortableVisibilityConverter;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Visibility;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;


use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;


#[AsCommand(
    name: 'Base',
    description: 'Add a short description for your command',
)]

abstract class BaseCommand extends Command
{
    protected $logger;

    protected $jobType;


    protected $profileStorage;

    protected $workingDir;
    protected $profileMonitor;    
    protected $yamlInput;
    protected $io;
    protected $mailer;
    protected array $jobs;
    protected bool $jobWithError = false;

    public function __construct(LoggerInterface $logger, MailerInterface $mailer)
    {
        parent::__construct();

        $this->logger= $logger;
        $this->jobType = null;   
        $this->mailer = $mailer;      
    }

    protected function configure(): void
    {
        $this
            ->addArgument('profileDirectory', InputArgument::REQUIRED, '')
            ->addArgument('profileFile', InputArgument::OPTIONAL, '') 
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $profileDirectory = $input->getArgument('profileDirectory');
        if ($profileDirectory) {
            $this->io->note(sprintf('You passed an argument: %s', $profileDirectory));
        } else {
            $this->addMonitor('failed', 'No commandline argument (profile directory)' );              
            throw new FileSystemException();
        }
        $this->createProfileStorage($profileDirectory);    
        $workingStorage = $this->createWorkingStorage($profileDirectory);   

        $profileFile = $input->getArgument('profileFile');
        if ($profileFile) {
            $this->io->note(sprintf('You passed an optional file: %s', $profileFile));
            $files[] = $profileFile;
        } else {
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
                    $this->addMonitor('failed', 'Profiles ' . $exception->getMessage(), 'ERROR' );  
                    throw new Exception($exception->getMessage());
                }
        }

        foreach ($files as $file) {
            try {
                $this->profileMonitor = array();
                try 
                {
                    $this->yamlInput = Yaml::parse($this->profileStorage->read($file));
                } catch (FilesystemException $exception) {
                    $this->addMonitor('failed', 'Profile ' . $file . ' ' . $exception->getMessage(), 'ERROR');  
                    throw new Exception($exception->getMessage());
                }   

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
                        $backupFile = $this->backupFileName($file, 'sql');
                        $backupJob = new MySqlBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage, $backupFile);
                        break;
                    case isset($this->yamlInput['source']['postgres']):
                        $this->addMonitor('postgres', $this->yamlInput['source']['postgres']['database']);  
                        $keysToCheck = ['username', 'password', 'host', 'database'];
                        $this->verifyConfig($keysToCheck, $this->yamlInput['source']['postgres']);
                        $backupFile = $this->backupFileName($file, 'sql');
                        $backupJob = new PostgresBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage, $backupFile);
                        break;     
                    case isset($this->yamlInput['source']['filesystem']):
                        $this->addMonitor('filesystem', $this->yamlInput['source']['filesystem']['path']);  
                        $keysToCheck = ['path'];
                        $this->verifyConfig($keysToCheck, $this->yamlInput['source']['filesystem']);
                        $backupFile = $this->backupFileName($file, 'tar.gz');
                        $backupJob = new FilesystemBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage, $backupFile);
                        break;  
                    case isset($this->yamlInput['source']['sftp']):
                        $this->addMonitor('fromType' , 'sftp');  
                        $this->addMonitor('sftp_host' , $this->yamlInput['source']['sftp']['host']);  
                        $this->addMonitor('sftp_port' , $this->yamlInput['source']['sftp']['port']);  
                        $this->addMonitor('sftp_username' , $this->yamlInput['source']['sftp']['username']);  
                        $this->addMonitor('sftp_path_private_key' , $this->yamlInput['source']['sftp']['path_private_key']);  
                        $this->addMonitor('sftp_use_agent' , $this->yamlInput['source']['sftp']['use_agent']);  
                        $this->addMonitor('sftp_timeout' , $this->yamlInput['source']['sftp']['timeout']);  
                        $this->addMonitor('sftp_max_tries' , $this->yamlInput['source']['sftp']['max_tries']);  
                        $this->addMonitor('sftp_connectivity_checker' , $this->yamlInput['source']['sftp']['connectivity_checker']);  
                        $this->addMonitor('sftp_path' , $this->yamlInput['source']['sftp']['path']);                      

                        $keysToCheck = ['host', 'username', 'path'];
                        $this->verifyConfig($keysToCheck, $this->yamlInput['source']['sftp']);
                        $backupFile = $this->backupFileName($file, 'tar.gz');
                        $backupJob = new SFTPBackup($this->yamlInput, $targetStorage, $this->workingDir, $workingStorage, $backupFile);
                        break;                                                             
                    default:
                        $this->addMonitor('failed', 'Unknown source type', 'ERROR'  );  
                        throw new Exception('Unknown source type');
                }

                $this->addMonitor('begin', new DateTime() );  
                if (!$this->doExecute($backupJob, $this->io, $this->logger)) {
                    $this->addMonitor('failed', 'Failed execute backupjob', 'ERROR') ;  
                } else {
                    $this->moveBackupFile($workingStorage, $targetStorage, $backupFile);
                    $this->retention( $targetStorage);
                }    
                $this->addMonitor('end', new DateTime() );  

            } catch (Exception $e) { 
                if (!isset($monitor['failed'] )  ) {
                    $this->addMonitor('failed', $e->getMessage() , 'ERROR') ;  
                } 
            }        
            $this->profileReporting();

            $this->io->note('Sleep');
            sleep(intval($_ENV['SLEEPTIME']));
        }    

        print_r($this->jobs);
        if ($this->jobWithError) {
            $this->io->error('Job(s) ended with errors');
            return Command::FAILURE;
        } else {
            $this->io->success('Job(s) ended successfully');
            return Command::SUCCESS;               
        }
    }

    public function moveBackupFile(Filesystem $workingStorage,Filesystem  $targetStorage, $file): void
    {
        $mountManager = new MountManager([
            'source' => $workingStorage,
            'target' => $targetStorage,
        ]);  
        $from = 'source://' . $file;
        $to = 'target://' . $file;
        $mountManager->move($from, $to);
        $this->addMonitor('backupFileMoved', 'Backup file ' . $file . ' moved to target') ;  
    }    

    public function retention(Filesystem $targetStorage): void
    {
        $shouldBeExtension = pathinfo($this->profileMonitor['backupFile'], PATHINFO_EXTENSION); 
        $shouldBeStartsWith = $this->profileMonitor['retentionPattern'];
        $this->addMonitor('retentionDays', $this->yamlInput['retention']['simple']['days']) ;  
      
        if (intval($this->yamlInput['retention']['simple']['days']) > 0) {
            $retentionDate = new DateTime() ;
            $retentionDate->modify('-' . $this->yamlInput['retention']['simple']['days']  . ' day');
            $this->addMonitor('retentionDate', 'Remove back-ups older than ' . $retentionDate->format('Y-m-d H:i:s')) ;  
            $files = $targetStorage->listContents('', FALSE);
            foreach ($files as $file) {
                if($file['type'] == 'file') {          
                    $path_parts = pathinfo($file->path());
                    if (strlen($path_parts['filename']) > 18) {
                        $dateInFileName = substr(str_replace('.tar', '', $path_parts['filename']), -14); 
                        if (str_starts_with($path_parts['filename'], $shouldBeStartsWith) && 
                            $path_parts['extension'] === $shouldBeExtension &&
                            is_numeric($dateInFileName)  &&
                            $retentionDate->format('YmdHis') >= $dateInFileName) {  
                                $targetStorage->delete($file->path() );
                                $this->addMonitor('retentionRemoved_' . $dateInFileName, $file->path() ) ;  
                        }
                    }
                }
            }
        }    
    }

    public function validateDate($date, $format = 'YmdHis')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    public function backupFileName($file, $ext='sql'): string
    {
        // sanitize filename
        $file_name_str = $this->yamlInput['name'] . '_' . $file; 
        // Replace all spaces with underscore. 
        $file_name_str = str_replace(' ', '_', $file_name_str); 
        // Replace special chars. 
        $file_name_str = preg_replace('/[^A-Za-z0-9\-\_]/', '_', $file_name_str); 
        // Replace multiple underscore with single one. 
        $file_name_str = preg_replace('/_+/', '_', $file_name_str); 

        $datumTijd = new DateTime();
        $backupFile = 'KOPIO_' . $file_name_str  . '_' . $datumTijd->format('YmdHis') . '.' . $ext;
        $this->addMonitor('backupFile', $backupFile);  
        $var = 'KOPIO_' . $file_name_str  . '_' ;
        $this->addMonitor('retentionPattern', $var); 

        return $backupFile;
    }    

    protected function addMonitor($item, $mes, $level = 'INFO'): void
    {
        if ($item === 'failed') {
            $level = 'ERROR';
        }
        if ($mes instanceof DateTime) {
            $message = $mes->format('c');
        } else {
            $message = $mes;            
        }

        $this->profileMonitor [$item] = $message;    

        $message = 'KOPIO ' . $item . ': ' . $message;
        switch (strtoupper($level) ) {
            case 'INFO':
                $this->logger->info($message);
                $this->io->note($message);      
                break;
            case 'ERROR':
                $this->logger->error($message);
                $this->io->error($message);
                $this->jobWithError = true;
                break;
            case 'NOTICE':
                $this->logger->notice($message);
                $this->io->note($message); 
                break;
            case 'DEBUG':
                $this->logger->debug($message);
                $this->io->note($message); 
                break;
            case 'WARNING':
                $this->logger->warning($message);
                $this->io->error($message);
                break;
            case 'ALERT ':
                $this->logger->alert($message);
                break;
            case 'CRITICAL':
                $this->logger->critical($message);
                $this->io->error($message);
                $this->jobWithError = true;
                break;
            case 'EMERGENCY':
                $this->logger->emergency($message);
                $this->io->error($message);
                $this->jobWithError = true;
                break;                                    
            default:
                $this->logger->info($message);
                $this->io->note($message); 
                break;
        }
    }

    protected function createProfileStorage($profileDirectory): void
    {    
        try
        {
            $adapter = new LocalFilesystemAdapter($profileDirectory);
            $this->profileStorage = new Filesystem($adapter);
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', 'Profiles directory: ' . $profileDirectory . ' ' . $exception->getMessage(), 'ERROR');  
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
            $this->addMonitor('failed', 'Profiles directory: ' . $this->workingDir . ' ' . $exception->getMessage(), 'ERROR');  
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
                    break;
                case isset($this->yamlInput['target']['sftp']):
                    $this->addMonitor('targetType' , 'sftp');  
                    $this->addMonitor('target' , $this->yamlInput['target']['sftp']['host']);  

                    $provider = new SftpConnectionProvider(
                        $this->yamlInput['target']['sftp']['host'],                 // host (required)
                        $this->yamlInput['target']['sftp']['username'],             // username (required)
                        $this->yamlInput['target']['sftp']['password'],             // password (optional, default: null) set to null if privateKey is used
                        $this->yamlInput['target']['sftp']['path_private_key'],     // private key (optional, default: null) can be used instead of password, set to null if password is set
                        $this->yamlInput['target']['sftp']['passphrase'],           // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
                        $this->yamlInput['target']['sftp']['port'],                 // port (optional, default: 22)
                        $this->yamlInput['target']['sftp']['use_agent'],            // use agent (optional, default: false)
                        $this->yamlInput['target']['sftp']['timeout'],              // timeout (optional, default: 10)
                        $this->yamlInput['target']['sftp']['max_tries'],            // max tries (optional, default: 4)
                        $this->yamlInput['target']['sftp']['fingerprint'],          // host fingerprint (optional, default: null),
                        $this->yamlInput['target']['sftp']['connectivity_checker'], // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
                    );                    

                    $adapter = new SftpAdapter(
                        $provider,
                        $this->yamlInput['target']['sftp']['path'],                 // root path (required)
                        PortableVisibilityConverter::fromArray([
                            'file' => [
                                'public' => 0640,
                                'private' => 0604,
                            ],
                            'dir' => [
                                'public' => 0740,
                                'private' => 7604,
                            ],
                        ])
                    ); 
                    $keysToCheck = ['host', 'username', 'path'];
                    $this->verifyConfig($keysToCheck, $this->yamlInput['target']['sftp']);
                    $targetStorage = new Filesystem($adapter);
                    break;   
/*                    
                case isset($this->yamlInput['target']['awss3']):
                    $this->addMonitor('targetType' , 'awss3');  
                    $this->addMonitor('target' , $this->yamlInput['target']['awss3']['host']);  

                    $client = new S3Client([
                        'region' =>   $this->yamlInput['target']['awss3']['region'], 
                        'version' =>  $this->yamlInput['target']['awss3']['version'],
                        'endpoint' => $this->yamlInput['target']['awss3']['endpoint'],
                        'credentials' => [
                            'key' => $this->yamlInput['target']['awss3']['access_key'],
                            'secret' => $this->yamlInput['target']['awss3']['secret'],
                        ],
                        'use_path_style_endpoint' => $this->yamlInput['target']['awss3']['use_path_style_endpoint'], 
                    ]);
                    $adapter = new AwsS3V3Adapter(
                        $client, 
                        $this->yamlInput['target']['awss3']['bucket'],  
                        $this->yamlInput['target']['awss3']['prefix'] 
                    );


                    $keysToCheck = ['host', 'username', 'path'];
                    $this->verifyConfig($keysToCheck, $this->yamlInput['target']['sftp']);
                    $targetStorage = new Filesystem($adapter);
                    break;                 
*/                   
                    
                default:
                    $this->addMonitor('failed', 'Unknown target filesystem', 'ERROR');  
                    throw new UnknownTargetException();
            }  
        } catch (FilesystemException $exception) {
            $this->addMonitor('failed', $exception->getMessage(), 'ERROR');  
            throw new FileSystemException($exception->getMessage());
        }

        return $targetStorage;                      
    }

    public function verifyConfig($keysToCheck, $array): void 
    {
        foreach($keysToCheck as $key) 
        {
            if (!array_key_exists($key, $array)) {
                $this->addMonitor('failed', 'No ' . $key . ' defined', 'ERROR');  
                throw new InvalidArgumentException('No ' . $key . ' defined');
            }    
        }
    } 
  
    protected abstract function doExecute(
            AbstractBackup $backupJob, 
            SymfonyStyle $io,
            LoggerInterface $logger
            ): bool;

    protected function profileReporting(): void
    {
        $sendReport = false;    
        $subject = $this->profileMonitor['notificationSubject'];   
        if ( !isset($monitor['failed'] )  ) {
            $subject .= ' Successfull: ';
            $this->jobs[$this->profileMonitor['profile'] . '/' . $this->profileMonitor['profileFile'] ] = 'successfull';
            if ($this->profileMonitor['notificationWhen'] === 'always') {
                $sendReport = true;
            } 
        } else {
            $subject .= ' FAILED: ';
            $this->jobs[$this->profileMonitor['profile'] . '/' . $this->profileMonitor['profileFile'] ] = 'FAILED';
            $sendReport = true;         
        } 

        if ($sendReport) {
            $subject .= " jobprofile " . $this->profileMonitor['profile'] . '/' . $this->profileMonitor['profileFile'] ;
            $message = $subject;
            $message .= PHP_EOL . print_r($this->profileMonitor, true) . PHP_EOL; 
            $email = (new Email())
                ->from($_ENV['EMAILFROM'])      
                ->to($this->profileMonitor['notificationEmailTo'])
                ->subject($subject)
                ->text($message)
                ->html('<pre>' . $message . '</pre>');
            $this->mailer->send($email);
        }    
    } 
}
