<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainer;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;

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

        $contentRepositoryRegistry = $this->getObject(ContentRepositoryRegistry::class);
        $contentRepositoryRegistry->resetFactoryInstance($contentRepositoryId);

        $this->contentRepository = $contentRepositoryRegistry->get($this->contentRepositoryId);
        /** @var ContentRepositoryMaintainer $contentRepositoryMaintainer */
        $contentRepositoryMaintainer = $contentRepositoryRegistry->buildService($contentRepositoryId, new ContentRepositoryMaintainerFactory());
        // Performance optimization: only run the setup once
        if (!self::$wasContentRepositorySetupCalled) {
            $contentRepositoryMaintainer->setUp();
            self::$wasContentRepositorySetupCalled = true;
        }

        $contentRepositoryMaintainer->prune();
    }
}
