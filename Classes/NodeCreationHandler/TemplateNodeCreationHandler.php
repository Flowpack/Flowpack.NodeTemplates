<?php

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\TemplateFactory;
use Flowpack\NodeTemplates\Infrastructure\ContentRepositoryTemplateHandler;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @var TemplateFactory
     * @Flow\Inject
     */
    protected $templateFactory;

    /**
     * @var ContentRepositoryTemplateHandler
     * @Flow\Inject
     */
    protected $contentRepositoryTemplateHandler;

    /**
     * @var FeedbackCollection
     * @Flow\Inject(lazy=false)
     */
    protected $feedbackCollection;

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
            $template = $this->templateFactory->createFromTemplateConfiguration($templateConfiguration, $evaluationContext, $caughtExceptions);
            $this->contentRepositoryTemplateHandler->apply($template, $node, $caughtExceptions);
        } finally {
            $caughtExceptions->serializeIntoFeedbackCollection($this->feedbackCollection, $node);
        }
    }
}
