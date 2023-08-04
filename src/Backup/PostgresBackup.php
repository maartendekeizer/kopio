<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\ConnectionErrorException;

use DateTime;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use League\Flysystem\Filesystem;

class PostgresBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage, string $backupFile)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage, $backupFile);
    }

    public function executeBackup(): string
    {
        $command = 'pg_dump -h ' . escapeshellarg($this->yamlInput['source']['postgres']['host']) .  ' -p '  . $this->yamlInput['source']['postgres']['port'] .  ' -U ' . escapeshellarg($this->yamlInput['source']['postgres']['username']) . ' -d ' .  escapeshellarg($this->yamlInput['source']['postgres']['database']) . ' -F p >' .  $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile ;
        if (is_null($this->yamlInput['source']['postgres']['password']) )   // NULL -> prompt, or pgpass (windows) 
        {
            $pwEscaped = NULL;
        } else {
            $pwEscaped = escapeshellarg($this->yamlInput['source']['postgres']['password']);          
            $command = 'PGPASSWORD=' . $pwEscaped .  'pg_dump -h ' . escapeshellarg($this->yamlInput['source']['postgres']['host']) .  ' -p '  . $this->yamlInput['source']['postgres']['port'] .  ' -U ' . escapeshellarg($this->yamlInput['source']['postgres']['username']) . ' -d ' .  escapeshellarg($this->yamlInput['source']['postgres']['database']) . ' -F p >' .  $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile ;
        }
   
        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        if (!$process->isSuccessful()) {
            return 'Command Failed';
        }

        return 'success';
    }
}