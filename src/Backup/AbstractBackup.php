<?php

declare(strict_types=1);
namespace App\Backup;

use App\Exception\CleanUpFailedException;
use DateTime;
use InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

abstract class AbstractBackup
{
    protected $name;
    protected $type;
    protected $source;
    protected $destination;
    protected $keysToCheck;
    protected $retention;
    protected $exception;

    public function __construct(string $name, string $type, array $source, string $destination, string $retention)
    {
      
        $this->name = $name;
        $this->type = $type;
        $this->source = $source;
        $this->destination = rtrim($destination, "/");
        $this->retention = $retention;
    }

    public function prepareBackup(): void 
    {
dd ($this->destination);        
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->destination);
    }

    public function verifyConfig(): void 
    {
        foreach($this->keysToCheck as $key) {
            if (!array_key_exists($key, $this->source)) {
                throw new InvalidArgumentException('No ' . $key . ' defined');
            }    
        }
    }

    public function generateRandomString($length = 8) 
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getName() 
    {
        return $this->name;
    }

    public function getType() 
    {
        return $this->type;
    }

    public function setType($type) 
    {
        $this->type = $type;
    }

    public function getException() 
    {
        return $this->exception;
    }

    public function setException(\Exception $exception) 
    {
        $this->exception = $exception;
    }

    abstract public function checkSource(): void;

    abstract public function executeBackup(): void;

    public function cleanUp()
    {
        $finder = new Finder();
        $files = $finder->in($this->destination)->files()->name('*');
        
        $filesystem = new Filesystem();
        
        foreach ($files as $file) {
            $fileDate = $file->getFilenameWithoutExtension();
            $fileDateObject = \DateTime::createFromFormat('YmdHis', $fileDate);

            $currentDate = new DateTime('now');
            
            $interval = $fileDateObject->diff($currentDate);

            if ($this->retention < $interval->days) {
                try {
                     $filesystem->remove($file->getRealPath());
                } catch(IOException $e) {
                    throw new CleanUpFailedException('Failed to create backup for file:' . $file->getRealPath());
                }
            }
        }
    }
}
