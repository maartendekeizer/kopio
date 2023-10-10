<?php

declare(strict_types=1);

namespace App\Target;

use App\Helper\Logger;
use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractFlysystem implements TargetInterface
{
    protected ?Filesystem $fs = null;

    protected string $runId;

    public function copy(Logger $logger, string $tmpPath, array $files): void
    {
        $fs = $this->getOrCreateFilesystem();

        $fs->createDirectory($this->runId);

        foreach ($files as $tmpFile => $fileName) {
            $fp = fopen($tmpPath . DIRECTORY_SEPARATOR . $tmpFile, 'r');
            $fs->writeStream(
                $this->runId . '/' . $fileName,
                $fp
            );
            if (is_resource($fp)) {
                fclose($fp);
            }
            unlink($tmpPath . DIRECTORY_SEPARATOR . $tmpFile);
        }
    }

    public function writeMetaData(Logger $logger, string $metaData): void
    {
        $fs = $this->getOrCreateFilesystem();
        $fs->write($this->runId . '/_meta.yaml', $metaData);
    }

    abstract protected function getOrCreateFilesystem(): Filesystem;

    public function list(Logger $logger): array
    {
        $yaml = new Yaml();
        $fs = $this->getOrCreateFilesystem();

        $metaFiles = $fs->listContents('', Filesystem::LIST_DEEP)
            ->filter(function (StorageAttributes $attr): bool {
                return $attr->isFile() && str_ends_with($attr->path(), '/_meta.yaml');
            })
            ->map(function (StorageAttributes $attr): string {
                return $attr->path();
            })
            ->toArray()
        ;

        $backups = [];
        foreach ($metaFiles as $path) {
            $location = substr($path, 0, strlen($path) - strlen('/_meta.yaml'));
            $backups[$location] = $yaml->parse($fs->read($path));
        }

        return $backups;
    }

    public function remove(Logger $logger, array $backupsToRemove): void
    {
        $fs = $this->getOrCreateFilesystem();
        foreach ($backupsToRemove as $backupLocation) {
            $fs->deleteDirectory($backupLocation);
        }
    }
}