<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Infrastructure\DbalClientInterface;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\EventStore\Exception\CheckpointException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManager;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;

/**
 * @property ObjectManager $objectManager
 */
trait ContentRepositoryTestTrait
{
    private readonly ContentRepository $contentRepository;

    private readonly ContentRepositoryId $contentRepositoryId;

    private static bool $wasContentRepositorySetupCalled = false;

    private function initCleanContentRepository(ContentRepositoryId $contentRepositoryId): void
    {
        if (!self::$wasContentRepositorySetupCalled) {
            // TODO super hacky and as we never clean up !!!
            $persistenceManager = $this->objectManager->get(PersistenceManager::class);
            if (is_callable([$persistenceManager, 'compile'])) {
                $result = $persistenceManager->compile();
                if ($result === false) {
                    self::markTestSkipped('Test skipped because setting up the persistence failed.');
                }
            }
        }

        $this->contentRepositoryId = $contentRepositoryId;

        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $registrySettings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.ContentRepositoryRegistry'
        );

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
        try {
            $this->contentRepository->resetProjectionStates();
        } catch (CheckpointException $checkpointException) {
            // Projection Reset may fail because the lock cannot be acquired
            // see working workaround: https://github.com/neos/neos-development-collection/blob/27f57c6cdec1deaa6a5fba04f85c2638b605f2e1/Neos.ContentRepository.Core/Tests/Behavior/Features/Bootstrap/EventSourcedTrait.php#L226-L304
            // we don't implement this workaround, since I didn't encounter this state in my simpler tests.
            if ($checkpointException->getCode() === 1652279016) {
                // another process is in the critical section; a.k.a.
                // the lock is acquired already by another process.

                // in case this actually happens, we should implement a retry
                throw new \RuntimeException('Projection reset failed because the lock cannot be acquired, please implement a retry.', 1686342087789, $checkpointException);
            } else {
                // some error error - we re-throw
                throw $checkpointException;
            }
        }
    }
}
