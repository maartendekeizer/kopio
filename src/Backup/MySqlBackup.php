<?php

declare(strict_types=1);
namespace App\Backup;

use DateTime;
use Symfony\Component\Process\Process;

use League\Flysystem\Filesystem;


class MySqlBackup extends AbstractBackup
{
    public function __construct(array $yamlInput, Filesystem $targetStorage, string $workingDir, Filesystem $workingStorage, string $backupFile)
    {
        parent::__construct($yamlInput, $targetStorage, $workingDir, $workingStorage, $backupFile);
    }

    public function executeBackup(): string
    {
        if (is_null($this->yamlInput['source']['mariadb']['password']) )   // In develop password = null
        {
            $pwEscaped = NULL;
        } else{
            $pwEscaped = escapeshellarg($this->yamlInput['source']['mariadb']['password']);          
        }
     
        $command = 'mysqldump --user=' . escapeshellarg($this->yamlInput['source']['mariadb']['username']) . " --password=" . $pwEscaped . " --host=" . escapeshellarg($this->yamlInput['source']['mariadb']['host']) . " --port=" . $this->yamlInput['source']['mariadb']['port'] . " " . escapeshellarg($this->yamlInput['source']['mariadb']['database']) . ' > ' .  $this->workingDir . DIRECTORY_SEPARATOR . $this->backupFile ;

        $process = Process::fromShellCommandline($command);
        $process->mustRun();
        if (!$process->isSuccessful()) {
            return 'Command Failed';
        }

        return 'success';
    }
}