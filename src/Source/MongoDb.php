<?php

declare(strict_types=1);

namespace App\Source;

use App\Helper\Logger;
use App\Profile\AbstractSource;
use App\Profile\Source\MongoDbSource;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;

class MongoDb implements SourceInterface
{
    private MongoDbSource $config;

    public function __construct(
        AbstractSource $config,
        private string $tmpPath,
        private string $runId
    )
    {
        if (($config instanceof MongoDbSource) === false) {
            throw new InvalidArgumentException('Config should be instance of MongoDbSource');
        }
        $this->config = $config;
    }

    public function execute(Logger $logger): array
    {
        $filename = $this->runId . '.' . 'dump.archive';

        $command = [$this->config->executable];
        $command[] = '--uri=' . $this->config->uri;
        $command[] = '--archive=' . $filename;

        $process = new Process($command, $this->tmpPath, timeout: 300);
        $process->run(function ($type, $buffer) use ($logger) {
            $logger->debug('Internal executable output', ['type' => $type, 'buffer' => $buffer]);
        });
        if ($process->isSuccessful() === false) {
            $logger->error('Internal executable exit code', ['code' => $process->getExitCode()]);
            throw new RuntimeException('Source failed during internal executable run');
        }

        // return list of writed filenames in tmp directory
        return [
            $filename => 'dump.archive'
        ];
    }
}