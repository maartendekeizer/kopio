<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\ConnectionErrorException;

use DateTime;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;

class FilesystemBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage, string $backupFile)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage, $backupFile);
    }

    public function executeBackup(): string
    {
        $command = 'tar -cvzf '. $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile . ' ' . escapeshellarg($this->yamlInput['source']['filesystem']['path']) ;

        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        if (!$process->isSuccessful()) {
            return 'Command Failed';
        }

        return 'success';
    }
}