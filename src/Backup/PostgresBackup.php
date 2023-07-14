<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\ConnectionErrorException;

use DateTime;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;

class PostgresBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage);
    }

    public function executeBackup(): array
    {
        if (is_null($this->yamlInput['source']['postgres']['password']) )   // In develop password = null
        {
            $pwEscaped = NULL;
        } else{
            $pwEscaped = escapeshellarg($this->yamlInput['source']['postgres']['password']);          
        }
        $datumTijd = new DateTime();
        $this->backupFile = 'KOPIO_' . $this->yamlInput['source']['postgres']['database'] . '_' . $datumTijd->format('YmdHis') . '.sql';
        $this->addMonitor('backupFile', $this->backupFile);      
// TODO PGPASSWORD (Unix)  pgpass 
        $command = 'pg_dump -h ' . escapeshellarg($this->yamlInput['source']['postgres']['host']) .  ' -p '  . $this->yamlInput['source']['postgres']['port'] .  ' -U ' . escapeshellarg($this->yamlInput['source']['postgres']['username']) . ' -d ' .  escapeshellarg($this->yamlInput['source']['postgres']['database']) . ' -F p >' .  $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile ;

        // todo     pg_dump -U username -W -F t database_name > c:\backup_file.tar
        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        if (!$process->isSuccessful()) {
            $this->addMonitor('failed', 'Failed to create Postgres backup for database: ' . $this->yamlInput['source']['postgres']['database'] . ' on host: ' . $this->yamlInput['source']['postgres']['host'] . ' with error output ' . $process->getErrorOutput() );  
            return $this->outputMonitor;
        }

        $this->moveFileToTarget($this->workingDir, $this->backupFile, true);

        return $this->outputMonitor;
    }
}