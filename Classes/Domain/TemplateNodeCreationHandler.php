<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\ExceptionHandler;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplateNotCreatedException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplatePartiallyCreatedException;
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
     * @var TemplateConfigurationProcessor
     * @Flow\Inject
     */
    protected $templateConfigurationProcessor;

    /**
     * @var ExceptionHandler
     * @Flow\Inject
     */
    protected $exceptionHandler;

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

        $caughtExceptions = CaughtExceptions::create();
        try {
            $template = $this->templateConfigurationProcessor->processTemplateConfiguration($templateConfiguration, $evaluationContext, $caughtExceptions);
            $this->exceptionHandler->handleAfterTemplateConfigurationProcessing($caughtExceptions, $nodeType, $commands->first->nodeAggregateId);

            $commands = (new NodeCreationService($subgraph, $contentRepository->getNodeTypeManager()))->apply($template, $commands, $caughtExceptions);
            $this->exceptionHandler->handleAfterNodeCreation($caughtExceptions, $nodeType, $commands->first->nodeAggregateId);

        } catch (TemplateNotCreatedException|TemplatePartiallyCreatedException $templateCreationException) {
        }

        return $commands;
    }
}
