<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepositoryRegistry\SubgraphCachingInMemory\SubgraphCachePool;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

trait ContentRepositoryTestTrait
{
    private readonly ContentRepository $contentRepository;

    private readonly ContentRepositoryId $contentRepositoryId;

    private static bool $wasContentRepositorySetupCalled = false;

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract protected function getObject(string $className): object;

    private function initCleanContentRepository(ContentRepositoryId $contentRepositoryId): void
    {
        $this->contentRepositoryId = $contentRepositoryId;

        $configurationManager = $this->getObject(ConfigurationManager::class);
        $registrySettings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.ContentRepositoryRegistry'
        );

        $contentRepositoryRegistry = new ContentRepositoryRegistry(
            $registrySettings,
            $this->getObject(ObjectManagerInterface::class),
            new SubgraphCachePool(),
        );

        $this->contentRepository = $contentRepositoryRegistry->get($this->contentRepositoryId);
        // Performance optimization: only run the setup once
        if (!self::$wasContentRepositorySetupCalled) {
            $this->contentRepository->setUp();
            self::$wasContentRepositorySetupCalled = true;
        }

        $connection = $this->getObject(Connection::class);

        // reset events and projections
        $eventTableName = sprintf('cr_%s_events', $this->contentRepositoryId->value);
        $connection->executeStatement('TRUNCATE ' . $eventTableName);
        $this->contentRepository->resetProjectionStates();
    }
}
