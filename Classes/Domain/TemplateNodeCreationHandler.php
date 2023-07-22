<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrorHandler;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationCommands;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @var NodeCreationService
     * @Flow\Inject
     */
    protected $nodeCreationService;

    /**
     * @var TemplateConfigurationProcessor
     * @Flow\Inject
     */
    protected $templateConfigurationProcessor;

    /**
     * @var ProcessingErrorHandler
     * @Flow\Inject
     */
    protected $processingErrorHandler;

    /**
     * Create child nodes and change properties upon node creation
     *
     * @param array $data incoming data from the creationDialog
     */
    public function handle(
        NodeCreationCommands $commands,
        array $data,
        ContentRepository $contentRepository
    ): NodeCreationCommands {
        $nodeType = $contentRepository->getNodeTypeManager()
            ->getNodeType($commands->first->nodeTypeName);
        $templateConfiguration = $nodeType->getOptions()['template'] ?? null;
        if (!$templateConfiguration) {
            return $commands;
        }

        $subgraph = $contentRepository->getContentGraph()->getSubgraph(
            $commands->first->contentStreamId,
            $commands->first->originDimensionSpacePoint->toDimensionSpacePoint(),
            VisibilityConstraints::frontend()
        );

        $evaluationContext = [
            'data' => $data,
            // todo evaluate which context variables
            'parentNode' => $subgraph->findNodeById($commands->first->parentNodeAggregateId),
            'subgraph' => $subgraph
        ];

        $processingErrors = ProcessingErrors::create();
        $template = $this->templateConfigurationProcessor->processTemplateConfiguration($templateConfiguration, $evaluationContext, $processingErrors);
        $shouldContinue = $this->processingErrorHandler->handleAfterTemplateConfigurationProcessing($processingErrors, $nodeType, $commands->first->nodeAggregateId);

        if (!$shouldContinue) {
            return $commands;
        }

        $additionalCommands = $this->nodeCreationService->apply($template, $commands, $contentRepository->getNodeTypeManager(), $subgraph, $processingErrors);
        $shouldContinue = $this->processingErrorHandler->handleAfterNodeCreation($processingErrors, $nodeType, $commands->first->nodeAggregateId);

        if (!$shouldContinue) {
            return $commands;
        }

        return $additionalCommands;
    }
}
