<?php

namespace Flowpack\NodeTemplates\Tests\Functional;


use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Infrastructure\DbalClientInterface;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\FakeClockFactory;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\FakeUserIdProviderFactory;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\EventStore\Exception\CheckpointException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManager;

/**
 * @property ObjectManager $objectManager
 */
trait ContentRepositoryTestTrait
{
    private readonly ContentRepository $contentRepository;

    private ContentRepositoryId $contentRepositoryId;

    private static $wasContentRepositorySetupCalled = false;

    private function initCleanContentRepository(): void
    {
        $this->contentRepositoryId ??= ContentRepositoryId::fromString('default');

        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $registrySettings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.ContentRepositoryRegistry'
        );

        $registrySettings['presets'][$this->contentRepositoryId->value]['userIdProvider']['factoryObjectName'] = FakeUserIdProviderFactory::class;
        $registrySettings['presets'][$this->contentRepositoryId->value]['clock']['factoryObjectName'] = FakeClockFactory::class;

        // no dimensions
        $registrySettings['contentRepositories'][$this->contentRepositoryId->value]['contentDimensions'] = [];

        $contentRepositoryRegistry = new ContentRepositoryRegistry(
            $registrySettings,
            $this->objectManager
        );

        $this->contentRepository = $contentRepositoryRegistry->get($this->contentRepositoryId);
        // Performance optimization: only run the setup once
        if (!self::$wasContentRepositorySetupCalled) {
            $this->contentRepository->setUp();
            self::$wasContentRepositorySetupCalled = true;
        }

        $connection = $this->objectManager->get(DbalClientInterface::class)->getConnection();

        // reset events and projections
        $eventTableName = sprintf('cr_%s_events', $this->contentRepositoryId->value);
        $connection->executeStatement('TRUNCATE ' . $eventTableName);
        // todo Projection Reset may fail because the lock cannot be acquired
        try {
            $this->contentRepository->resetProjectionStates();
        } catch (CheckpointException $checkpointException) {
            if ($checkpointException->getCode() === 1652279016) {
                // another process is in the critical section; a.k.a.
                // the lock is acquired already by another process.

                // in case this actually happens, we should implement a retry
                throw new \RuntimeException('Projection reset failed because the lock cannot be acquired', 1686342087789, $checkpointException);
            } else {
                // some error error - we re-throw
                throw $checkpointException;
            }
        }
    }
}
