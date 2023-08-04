<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\CleanUpFailedException;
use DateTime;

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
   

    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage, string $backupFile)
    {
        $this->yamlInput = $yamlInput;
        $this->targetStorage = $targetStorage;
        $this->workingDir = $workingDir;
        $this->workingStorage = $workingStorage;
        $this->backupFile = $backupFile;
    }

    abstract public function executeBackup(): string;
 

}

