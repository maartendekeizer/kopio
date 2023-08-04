<?php
declare(strict_types=1);
namespace App\Backup;

use Symfony\Component\Process\Process;
use Exception;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\MountManager;

use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;


class SFTPBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage, string $backupFile)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage, $backupFile);
    }

    protected function createFromStorage(): Filesystem
    {
        try
        {
            $provider = new SftpConnectionProvider(
                $this->yamlInput['source']['sftp']['host'],                 // host (required)
                $this->yamlInput['source']['sftp']['username'],             // username (required)
                $this->yamlInput['source']['sftp']['password'],             // password (optional, default: null) set to null if privateKey is used
                $this->yamlInput['source']['sftp']['path_private_key'],     // private key (optional, default: null) can be used instead of password, set to null if password is set
                $this->yamlInput['source']['sftp']['passphrase'],           // passphrase (optional, default: null), set to null if privateKey is not used or has no passphrase
                $this->yamlInput['source']['sftp']['port'],                 // port (optional, default: 22)
                $this->yamlInput['source']['sftp']['use_agent'],            // use agent (optional, default: false)
                $this->yamlInput['source']['sftp']['timeout'],              // timeout (optional, default: 10)
                $this->yamlInput['source']['sftp']['max_tries'],            // max tries (optional, default: 4)
                $this->yamlInput['source']['sftp']['fingerprint'],          // host fingerprint (optional, default: null),
                $this->yamlInput['source']['sftp']['connectivity_checker'], // connectivity checker (must be an implementation of 'League\Flysystem\PhpseclibV2\ConnectivityChecker' to check if a connection can be established (optional, omit if you don't need some special handling for setting reliable connections)
            );

            $adapter = new SftpAdapter(
                $provider,
                $this->yamlInput['source']['sftp']['path'],                 // root path (required)
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

            $fromStorage = new Filesystem( $adapter);   
        } catch (FilesystemException $exception) {
            throw new Exception($exception->getMessage());
        }
        return $fromStorage;                      
    }

    public function executeBackup(): string
    {
        try
        {        
            $fromStorage = $this->createFromStorage();
         
            $mountManager = new MountManager([
                'source' => $fromStorage,
                'target' => $this->workingStorage,
            ]);  
        
            $dirToCompress = pathinfo(pathinfo($this->backupFile, PATHINFO_FILENAME), PATHINFO_FILENAME);
            $files = $fromStorage->listContents('', true);
   
            foreach ($files as $file) {
                if($file['type'] == 'dir')
                {
                    // flysytem creates dir automatically, this covers empty dir
                    $mountManager->createDirectory('target://' . $dirToCompress. '/' .$file['path']);
                }
                if($file['type'] == 'file')
                {
                    $from = 'source://' . $file['path'];
                    $to = 'target://' . $dirToCompress . '/' . $file['path'];
                    $mountManager->copy($from, $to);
                }   
            }
            $removePrefix = ' -C ' . $this->workingDir . DIRECTORY_SEPARATOR . $dirToCompress . DIRECTORY_SEPARATOR . '. ';
            $command = 'tar ' . $removePrefix .  ' -cvzf '. $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile . ' ' . escapeshellarg($this->workingDir . DIRECTORY_SEPARATOR . $dirToCompress ) ;
            $process = Process::fromShellCommandline($command);
            $process->mustRun();
            $this->workingStorage->deleteDirectory($dirToCompress);
            if (!$process->isSuccessful()) {
                return 'Command Failed';
            }

        } catch (Exception $e) { 
            return 'Failed' . $e->getMessage();        
        }            
        return 'success';   
    }
}