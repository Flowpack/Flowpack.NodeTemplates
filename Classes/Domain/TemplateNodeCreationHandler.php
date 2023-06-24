<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrorHandler;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @var NodeCreationService
     * @Flow\Inject
     */
    protected $nodeCreationService;

    /**
     * @var NodeTypeManager
     * @Flow\Inject
     */
    protected $nodeTypeManager;

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
     * @param NodeInterface $node The newly created node
     * @param array $data incoming data from the creationDialog
     */
    public function handle(NodeInterface $node, array $data): void
    {
        if (!$node->getNodeType()->hasConfiguration('options.template')) {
            return;
        }

        $evaluationContext = [
            'data' => $data,
            // deprectated will be removed in 3.0
            'triggeringNode' => $node,
            'parentNode' => $node->getParent()
        ];

        $templateConfiguration = $node->getNodeType()->getConfiguration('options.template');

        $processingErrors = ProcessingErrors::create();

        $template = $this->templateConfigurationProcessor->processTemplateConfiguration($templateConfiguration, $evaluationContext, $processingErrors);
        $shouldContinue = $this->processingErrorHandler->handleAfterTemplateConfigurationProcessing($processingErrors, $node);

        if (!$shouldContinue) {
            return;
        }

        $nodeMutators = $this->nodeCreationService->createMutatorsForRootTemplate($template, $node->getNodeType(), $this->nodeTypeManager, $node->getContext(), $processingErrors);

        $shouldContinue = $this->processingErrorHandler->handleAfterNodeCreation($processingErrors, $node);

        if (!$shouldContinue) {
            return;
        }

        $nodeMutators->executeWithStartingNode($node);
    }
}
