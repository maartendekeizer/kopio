<?php

declare(strict_types=1);

namespace App\Source;

use App\Helper\Logger;
use App\Profile\AbstractSource;
use App\Profile\Source\SftpSource;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\MountManager;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Process\Process;

class Sftp implements SourceInterface
{
    private SftpSource $config;

    private string $downloadDirName;

    public function __construct(
        AbstractSource $config,
        private string $tmpPath,
        private string $runId
    )
    {
        if (($config instanceof SftpSource) === false) {
            throw new InvalidArgumentException('Config should be instance of SftpSource');
        }
        $this->config = $config;
        $this->downloadDirName = 'download.' . uniqid();
    }

    public function execute(Logger $logger): array
    {
        $filename = 'acthive.' . uniqid() . '.tar.gz';

        $sftpFs = $this->createSftpFilesystem();
        $tmpFs = $this->createTmpFilesystem();

        $mountManager = new MountManager([
            'source' => $sftpFs,
            'tmp' => $tmpFs
        ]);

        // create a list of files to copy
        $files = $sftpFs->listContents('', true);

        // copy all files
        foreach ($files as $file) {
            if ($file['type'] === 'dir') {
                $mountManager->createDirectory('tmp://' . $file['path']);
            } elseif($file['type'] === 'file') {
                $mountManager->copy('source://' . $file['path'], 'tmp://' . $file['path']);
            }
        }

        // make archive
        $filename = uniqid() . '.tar.gz';

        $command = ['tar'];
        $command[] = '--create';
        $command[] = '--gzip';
        $command[] = '--file=' . $filename;
        $command[] = $this->downloadDirName;

        $process = new Process($command, $this->tmpPath, timeout: 300);
        $logger->debug('Internal executable command', ['line' => $process->getCommandLine()]);
        $process->run(function ($type, $buffer) use ($logger) {
            $logger->debug('Internal executable output', ['type' => $type, 'buffer' => $buffer]);
        });
        if ($process->isSuccessful() === false) {
            $logger->error('Internal executable exit code', ['code' => $process->getExitCode()]);
            throw new RuntimeException('Source failed during internal executable run');
        }

        // delete tmp
        $fs = new SymfonyFilesystem();
        $fs->remove($this->tmpPath . DIRECTORY_SEPARATOR . $this->downloadDirName);

        return [
            $filename => 'download.tar.gz'
        ];
    }

    private function createSftpFilesystem(): Filesystem
    {
        $provider = new SftpConnectionProvider(
            $this->config->host,
            $this->config->username,
            $this->config->password,
            $this->config->pathPrivateKey,
            $this->config->passphrasePrivateKey,
            $this->config->port,
            $this->config->useAgent,
            hostFingerprint: $this->config->fingerprint
        );

        $adapter = new SftpAdapter(
            $provider,
            $this->config->path,
            null
        );

        return new Filesystem($adapter);
    }

    private function createTmpFilesystem(): Filesystem
    {
        $fs = new SymfonyFilesystem();
        $fs->mkdir($this->tmpPath . DIRECTORY_SEPARATOR . $this->downloadDirName);

        $adapter = new LocalFilesystemAdapter($this->tmpPath . DIRECTORY_SEPARATOR . $this->downloadDirName);

        return new Filesystem($adapter);
    }
}