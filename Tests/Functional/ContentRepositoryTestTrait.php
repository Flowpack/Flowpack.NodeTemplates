<?php

namespace Flowpack\NodeTemplates\Tests\Functional;


use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\FakeClockFactory;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\FakeUserIdProviderFactory;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\ObjectManagement\ObjectManager;

/**
 * @property ObjectManager $objectManager
 */
trait ContentRepositoryTestTrait
{
    private readonly ContentRepository $contentRepository;

    private ContentRepositoryId $contentRepositoryId;

    private function initCleanContentRepository(): void
    {
        $this->contentRepositoryId ??= ContentRepositoryId::fromString('default');

        $configurationManager = $this->objectManager->get(ConfigurationManager::class);
        $registrySettings = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.ContentRepositoryRegistry'
        );

        // in case we do not have tests annotated with @adapters=Postgres, we
        // REMOVE the Postgres projection from the Registry settings. This way, we won't trigger
        // Postgres projection catchup for tests which are not yet postgres-aware.
        //
        // This is to make the testcases more stable and deterministic. We can remove this workaround
        // once the Postgres adapter is fully ready.
        unset($registrySettings['presets'][$this->contentRepositoryId->value]['projections']['Neos.ContentGraph.PostgreSQLAdapter:Hypergraph']);

        $registrySettings['presets'][$this->contentRepositoryId->value]['userIdProvider']['factoryObjectName'] = FakeUserIdProviderFactory::class;
        $registrySettings['presets'][$this->contentRepositoryId->value]['clock']['factoryObjectName'] = FakeClockFactory::class;

        // no dimensions
        $registrySettings['contentRepositories'][$this->contentRepositoryId->value]['contentDimensions'] = [];

        $contentRepositoryRegistry = new ContentRepositoryRegistry(
            $registrySettings,
            $this->objectManager
        );

        $this->contentRepository = $contentRepositoryRegistry->get($this->contentRepositoryId);
        // // Big performance optimization: only run the setup once - DRAMATICALLY reduces test time
        // if ($this->alwaysRunContentRepositorySetup || !self::$wasContentRepositorySetupCalled) {
        $this->contentRepository->setUp();
        //     self::$wasContentRepositorySetupCalled = true;
        // }
    }
}
