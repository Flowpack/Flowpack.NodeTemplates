<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\ExceptionHandler;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplateNotCreatedException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplatePartiallyCreatedException;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
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

            $nodeMutators = (new NodeCreationService($node->getContext()))->apply($template, $node, $caughtExceptions);
            $nodeMutators->apply($node);
            $this->exceptionHandler->handleAfterNodeCreation($caughtExceptions, $node);
        } catch (TemplateNotCreatedException|TemplatePartiallyCreatedException $templateCreationException) {
        }
    }
}
