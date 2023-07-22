<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\ExceptionHandler;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplateNotCreatedException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplatePartiallyCreatedException;
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
     * @var ExceptionHandler
     * @Flow\Inject
     */
    protected $exceptionHandler;

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
            'triggeringNode' => $node,
        ];

        $templateConfiguration = $node->getNodeType()->getConfiguration('options.template');

        $caughtExceptions = CaughtExceptions::create();
        try {
            $template = $this->templateConfigurationProcessor->processTemplateConfiguration($templateConfiguration, $evaluationContext, $caughtExceptions);
            $this->exceptionHandler->handleAfterTemplateConfigurationProcessing($caughtExceptions, $node);

            $nodeMutators = $this->nodeCreationService->createMutatorsForRootTemplate($template, $node->getNodeType(), $this->nodeTypeManager, $node->getContext(), $caughtExceptions);
            $nodeMutators->executeWithStartingNode($node);

            $this->exceptionHandler->handleAfterNodeCreation($caughtExceptions, $node);
        } catch (TemplateNotCreatedException|TemplatePartiallyCreatedException $templateCreationException) {
        }
    }
}
