<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Logger
{
    private array $data = [];

    public function __construct(
        private OutputInterface $output,
        private LoggerInterface $logger,
        private array $streams
    )
    {
        //
    }

    public function withData(array $data): self
    {
        $new = new self($this->output, $this->logger, $this->streams);
        $new->data = array_merge($this->data, $data);
        return $new;
    }

    public function withStream($stream): self
    {
        $streams = $this->streams;
        $streams[] = $stream;
        $new = new self($this->output, $this->logger, $streams);
        return $new;
    }

    public function debug(string $message, array $data = []): void
    {
        $this->log('debug', $message, $data);
    }

    public function info(string $message, array $data = []): void
    {
        $this->log('info', $message, $data);
    }

    public function error(string $message, array $data = []): void
    {
        $this->log('error', $message, $data);
    }

    public function log(string $level, string $message, array $data): void
    {
        $data = array_merge($this->data, $data);

        $prefix = date('c') . ' ' . str_pad(strtoupper($level), 8, ' ', STR_PAD_RIGHT);

        $verboseLevel = match (strtoupper($level)) {
            'DEBUG' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            'INFO' => OutputInterface::VERBOSITY_VERBOSE,
            default => OutputInterface::VERBOSITY_NORMAL
        };

        $this->logger->log($level, '[profile execution] ' . $message, $data);

        $this->output->writeln($prefix . $message, $verboseLevel);
        $this->output->writeln(str_repeat(' ', strlen($prefix)) . json_encode($data), $verboseLevel);

        foreach ($this->streams as $stream) {
            fwrite($stream, $prefix . $message . "\t\t" . json_encode($data) . PHP_EOL);
        }
    }

}