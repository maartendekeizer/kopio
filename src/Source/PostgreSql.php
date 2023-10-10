<?php

declare(strict_types=1);

namespace App\Source;

use App\Helper\Logger;
use App\Profile\AbstractSource;
use App\Profile\Source\PostgreSqlSource;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class PostgreSql implements SourceInterface
{
    private PostgreSqlSource $config;

    public function __construct(
        AbstractSource $config,
        private string $tmpPath,
        private string $runId
    )
    {
        if (($config instanceof PostgreSqlSource) === false) {
            throw new InvalidArgumentException('Config should be instance of PostgreSqlSource');
        }
        $this->config = $config;
    }

    public function execute(Logger $logger): array
    {
        $filename = $this->runId . '.' . 'dump.sql';

        $env = [];

        $command = [$this->config->executable];
        $command[] = '--host=' . $this->config->host;
        $command[] = '--port=' . $this->config->port;
        if ($this->config->username) {
            $command[] = '--username=' . $this->config->username;
        }
        if ($this->config->password) {
            $env['PGPASSWORD'] = $this->config->password;
        }
        $command[] = '--no-password';
        $command[] = '--format=plain';
        $command[] = '--no-owner';
        $command[] = '--dbname=' . $this->config->database;
        $command[] = '--file=' . $filename;

        $process = new Process($command, $this->tmpPath, $env, timeout: 300);
        $process->run(function ($type, $buffer) use ($logger) {
            $logger->debug('Internal executable output', ['type' => $type, 'buffer' => $buffer]);
        });
        if ($process->isSuccessful() === false) {
            $logger->error('Internal executable exit code', ['code' => $process->getExitCode()]);
            throw new RuntimeException('Source failed during internal executable run');
        }

        // return list of writed filenames in tmp directory
        return [
            $filename => 'dump.sql'
        ];
    }
}