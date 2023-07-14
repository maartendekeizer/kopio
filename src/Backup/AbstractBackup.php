<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\CleanUpFailedException;
use DateTime;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\IOException;


use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\FilesystemException;

use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Process\Process;


use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;


abstract class AbstractBackup
{
    protected $targetStorage;
    protected $workingDir;
    protected $workingStorage;

    protected $keysToCheck;
    protected $exception;

    protected $backupFile;
    protected $yamlInput;
    protected $outputMonitor;
   

    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage)
    {
        $this->yamlInput = $yamlInput;
        $this->targetStorage = $targetStorage;
        $this->workingDir = $workingDir;
        $this->workingStorage = $workingStorage;

    }

    abstract public function executeBackup(): array;

    public function moveFileToTarget(string $fromDir, string $file, bool $deleteFrom = false)
    {

        $mountManager = new MountManager([
            'source' => $this->workingStorage,
            'target' => $this->targetStorage,

        ]);  
        
        $from = 'source://' . $file;
        $to = 'target://' . $file;
        if ($deleteFrom)
        {
            $mountManager->move($from, $to);
        } else {
            $mountManager->copy($from, $to);
        }    
    }

    protected function addMonitor($item, $message): void
    {
        $this->outputMonitor [$item] = $message;  
    }    

    public function cleanUp()
    {
        dump ("TODO: cleanup");
        /*
            $finder = new Finder();
            //        $files = $finder->in($this->destination)->files()->name('*');
            $files = $finder->in('c:\temp\*.sql');
            
            $filesystem = new Filesystem();
            
            foreach ($files as $file) {
                $fileDate = $file->getFilenameWithoutExtension();
                $fileDateObject = \DateTime::createFromFormat('YmdHis', $fileDate);

                $currentDate = new DateTime('now');
                
                $interval = $fileDateObject->diff($currentDate);

                if ($this->retention < $interval->days) {
                    try {
                        $filesystem->remove($file->getRealPath());
                    } catch(IOException $e) {
                        throw new CleanUpFailedException('Failed to create backup for file:' . $file->getRealPath());
                    }
                }
            }
        */          
    }
}

