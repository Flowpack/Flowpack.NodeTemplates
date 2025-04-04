<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrorHandler;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationHandlerFactoryInterface;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationHandlerInterface;

final class TemplateNodeCreationHandlerFactory implements NodeCreationHandlerFactoryInterface
{
    #[Flow\Inject]
    protected NodeCreationService $nodeCreationService;

    #[Flow\Inject]
    protected TemplateConfigurationProcessor $templateConfigurationProcessor;

    #[Flow\Inject]
    protected ProcessingErrorHandler $processingErrorHandler;

    public function build(ContentRepository $contentRepository): NodeCreationHandlerInterface
    {
        return new TemplateNodeCreationHandler(
            $contentRepository,
            $this->nodeCreationService,
            $this->templateConfigurationProcessor,
            $this->processingErrorHandler
        );
    }
}
