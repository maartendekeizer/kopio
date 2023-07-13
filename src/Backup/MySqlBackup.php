<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\ConnectionErrorException;

use DateTime;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use League\Flysystem\Filesystem;
use League\Flysystem\MountManager;

class MySqlBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage);
    }

    public function checkSource(): string
    {
        try {
            $conn = new \PDO("mysql:host=" . $this->yamlInput['source']['mariadb']['host'] . ";port=" . $this->yamlInput['source']['mariadb']['port'] . ";dbname=".$this->yamlInput['source']['mariadb']['database'], $this->yamlInput['source']['mariadb']['username'], $this->yamlInput['source']['mariadb']['password']);
        } catch(\PDOException $e) {
            return $e->getMessage();
        }
        return '';
    }

    public function executeBackup(): array
    {
        if (is_null($this->yamlInput['source']['mariadb']['password']) )   // In develop password = null
        {
            $pwEscaped = NULL;
        } else{
            $pwEscaped = escapeshellarg($this->yamlInput['source']['mariadb']['password']);          
        }
        $datumTijd = new DateTime();
        $this->backupFile = $this->yamlInput['source']['mariadb']['database'] . '_' . $datumTijd->format('YmdHis') . '.sql';
        $this->addMonitor('backupFile', $this->backupFile);      

//TODO:  --routines   --single-transaction (InnoDB)   --quick (InnoDB)
        $command = 'mysqldump --user=' . escapeshellarg($this->yamlInput['source']['mariadb']['username']) . " --password=" . $pwEscaped . " --host=" . escapeshellarg($this->yamlInput['source']['mariadb']['host']) . " --port=" . $this->yamlInput['source']['mariadb']['port'] . " " . escapeshellarg($this->yamlInput['source']['mariadb']['database']) . ' > ' .  $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile ;
        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        if (!$process->isSuccessful()) {
            $this->addMonitor('failed', 'Failed to create MySql backup for database: ' . $this->yamlInput['source']['mariadb']['database'] . ' on host: ' . $this->yamlInput['source']['mariadb']['host'] . ' with error output ' . $process->getErrorOutput() );  
            return $this->outputMonitor;
        }

        $this->moveFileToTarget($this->workingDir, $this->backupFile, true);

        return $this->outputMonitor;
    }
}