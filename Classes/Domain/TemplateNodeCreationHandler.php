<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrorHandler;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationCommands;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationElements;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationHandlerInterface;

final readonly class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    public function __construct(
        private ContentRepository $contentRepository,
        private NodeCreationService $nodeCreationService,
        private TemplateConfigurationProcessor $templateConfigurationProcessor,
        private ProcessingErrorHandler $processingErrorHandler
    ) {
    }

    /**
     * Create child nodes and change properties upon node creation
     */
    public function handle(
        NodeCreationCommands $commands,
        NodeCreationElements $elements
    ): NodeCreationCommands {
        $nodeType = $this->contentRepository->getNodeTypeManager()
            ->getNodeType($commands->first->nodeTypeName);

        $templateConfiguration = $nodeType?->getOptions()['template'] ?? null;
        if (!$templateConfiguration) {
            return $commands;
        }

        $subgraph = $this->contentRepository->getContentGraph($commands->first->workspaceName)->getSubgraph(
            $commands->first->originDimensionSpacePoint->toDimensionSpacePoint(),
            VisibilityConstraints::frontend()
        );

        $evaluationContext = [
            'data' => iterator_to_array($elements->serialized()),
            'site' => $subgraph->findClosestNode($commands->first->parentNodeAggregateId, FindClosestNodeFilter::create(NodeTypeNameFactory::NAME_SITE)),
            'parentNode' => $subgraph->findNodeById($commands->first->parentNodeAggregateId)
        ];

        $processingErrors = ProcessingErrors::create();
        $template = $this->templateConfigurationProcessor->processTemplateConfiguration($templateConfiguration, $evaluationContext, $processingErrors);
        $shouldContinue = $this->processingErrorHandler->handleAfterTemplateConfigurationProcessing($processingErrors, $nodeType, $commands->first->nodeAggregateId);

        if (!$shouldContinue) {
            return $commands;
        }

        $additionalCommands = $this->nodeCreationService->apply($template, $commands, $this->contentRepository->getNodeTypeManager(), $subgraph, $nodeType, $processingErrors);
        $shouldContinue = $this->processingErrorHandler->handleAfterNodeCreation($processingErrors, $nodeType, $commands->first->nodeAggregateId);

        if (!$shouldContinue) {
            return $commands;
        }

        return $additionalCommands;
    }
}
