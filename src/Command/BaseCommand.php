<?php

declare(strict_types=1);

namespace App\Command;

use App\Helper\Logger;
use App\Notification\NotificationInterface;
use App\Profile\AbstractNotification;
use App\Profile\ExecutorConfigurationInterface;
use App\Profile\Profile;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Yaml\Yaml;

abstract class BaseCommand extends Command implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private AsciiSlugger $slugger;

    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $runLogger
    )
    {
        parent::__construct();
        $this->slugger = new AsciiSlugger('en');
        $this->logger = $runLogger;
    }

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to a backup profile or directory with backup profiles');
    }

    public function getBackupProfilesFromPath(string $path): array
    {
        if (is_file($path)) {
            return [$path];
        }

        return array_map(function (SplFileInfo $fileInfo) {
            return $fileInfo->getPathname();
        }, iterator_to_array(Finder::create()->in($path)->files()->name(['*.yaml', '*.yml'])->ignoreDotFiles(true)->sortByName()));
    }

    protected function sendNotifications(Logger $logger, Profile $profile, string $runId, string $on, array $data): void
    {
        foreach ($profile->getNotifications($on) as $notification) {
            assert($notification instanceof AbstractNotification);

            $executor = $this->createExecutor($logger, $notification, 'notification', NotificationInterface::class, [
                $notification,
                $profile->name,
                $runId
            ]);

            assert($executor instanceof NotificationInterface); /** @var NotificationInterface $executor */
            $logger->debug('Notification component created successfully', []);

            $logger->info('Notification component execution', []);
            $executor->execute($logger, 'backup', $data);
            $logger->info('Notification component finished', []);
        }
    }

    protected function readProfile(Logger $logger, string $path, array &$metaData): Profile
    {
        $logger->debug('Start reading profile', ['path' => $path]);
        $data = Yaml::parseFile($path);
        // run validator
        $profile = Profile::fromArray($data);
        $metaData['name'] = $profile->name;
        $logger->debug('Profile readed', ['path' => $path, 'profile' => $profile->name]);
        return $profile;
    }

    protected function getRunId(Logger $logger, Profile $profile, \DateTimeInterface $commandStart, \DateTimeInterface $runStart, array &$metaData): string
    {
        $runId = $this->slugger->slug($profile->name, '-') . '.' . $commandStart->format('YmdHis') . '.' . $runStart->format('YmdHis');
        $metaData['runId'] = $runId;
        $logger->debug('RunId generated', ['profile' => $profile->name, 'runId' => $runId]);
        return $runId;
    }

    protected function createExecutor(Logger $logger, ExecutorConfigurationInterface $executorConfiguration, string $componentName, string $interfaceFqcn, array $constructorArgs): object
    {
        $class = $executorConfiguration->getExecutorClass();

        $logger->info('Create ' . $componentName . ' component', ['class' => $class]);

        $reflectionClass = new ReflectionClass($class);
        if ($reflectionClass->implementsInterface($interfaceFqcn) === false) {
            $logger->error('Executor class does not implements expected interface', ['fqcn' => $interfaceFqcn]);
            throw new RuntimeException('Invalid executor');
        }

        $executor = $reflectionClass->newInstanceArgs($constructorArgs);

        foreach ($executorConfiguration->getExecutorCalls() as $method => $serviceName) {
            $reflectionClass->getMethod($method)->invokeArgs($executor, [
                $this->container->get($serviceName)
            ]);
        }

        return $executor;
    }
}