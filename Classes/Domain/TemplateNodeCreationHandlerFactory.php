<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryDependencies;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;

/**
 * @implements ContentRepositoryServiceFactoryInterface<TemplateNodeCreationHandler>
 */
final class TemplateNodeCreationHandlerFactory implements ContentRepositoryServiceFactoryInterface
{
    public function build(ContentRepositoryServiceFactoryDependencies $serviceFactoryDependencies): ContentRepositoryServiceInterface {
        return new TemplateNodeCreationHandler($serviceFactoryDependencies->contentRepository);
    }
}
