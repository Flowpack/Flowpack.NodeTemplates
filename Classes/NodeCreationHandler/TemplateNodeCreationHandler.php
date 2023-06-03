<?php

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\EelException;
use Flowpack\NodeTemplates\Domain\TemplateFactory;
use Flowpack\NodeTemplates\Infrastructure\ContentRepositoryTemplateHandler;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
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
    protected $templateContentRepositoryApplier;

    /**
     * @var FeedbackCollection
     * @Flow\Inject
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
        if ($node->getNodeType()->hasConfiguration('options.template')) {
            $templateConfiguration = $node->getNodeType()->getConfiguration('options.template');
        } else {
            return;
        }

        $context = [
            'data' => $data,
            'triggeringNode' => $node,
        ];

        try {
            $template = $this->templateFactory->createFromTemplateConfiguration($templateConfiguration, $context, CaughtExceptions::create());
            $this->templateContentRepositoryApplier->apply($template, $node);
        } catch (\Exception $exception) {
            $this->handleExceptions($node, $exception);
        }
    }

    /**
     * Known exceptions are logged to the Neos.Ui and caught
     *
     * @param NodeInterface $node the newly created node
     * @param \Exception $exception the exception to handle
     * @throws \Exception in case the exception is unknown and cant be handled
     */
    private function handleExceptions(NodeInterface $node, \Exception $exception): void
    {
        $nodeTemplateError = new Error();
        $nodeTemplateError->setMessage(sprintf('Template for "%s" only partially applied. Please check the newly created nodes.', $node->getNodeType()->getLabel()));

        if ($exception instanceof NodeConstraintException) {
            $this->feedbackCollection->add(
                $nodeTemplateError
            );

            $error = new Error();
            $error->setMessage($exception->getMessage());
            $this->feedbackCollection->add(
                $error
            );
            return;
        }
        if ($exception instanceof EelException) {
            $this->feedbackCollection->add(
                $nodeTemplateError
            );

            $error = new Error();
            $error->setMessage(
                $exception->getMessage()
            );
            $this->feedbackCollection->add(
                $error
            );

            $level = 0;
            while (($exception = $exception->getPrevious()) && $level <= 8) {
                $level++;
                $error = new Error();
                $error->setMessage(
                    sprintf('%s [%s(%s)]', $exception->getMessage(), get_class($exception), $exception->getCode())
                );
                $this->feedbackCollection->add(
                    $error
                );
            }
            return;
        }
        throw $exception;
    }
}
