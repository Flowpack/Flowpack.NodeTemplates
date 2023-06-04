<?php

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\TemplateFactory;
use Flowpack\NodeTemplates\Domain\TemplatePartiallyAppliedException;
use Flowpack\NodeTemplates\Infrastructure\ContentRepositoryTemplateHandler;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     * @Flow\Inject
     */
    protected $logger;

    /**
     * @var ThrowableStorageInterface
     * @Flow\Inject
     */
    protected $throwableStorage;

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
            $this->handleCaughtExceptionsForNode($caughtExceptions, $node);
        }
    }

    public function handleCaughtExceptionsForNode(CaughtExceptions $caughtExceptions, NodeInterface $node): void
    {
        if ($caughtExceptions->hasExceptions()) {
            return;
        }

        $initialMessageInCaseOfException = sprintf('Template for "%s" only partially applied. Please check the newly created nodes beneath %s.', $node->getNodeType()->getLabel(), (string)$node);

        $lastException = null;
        $messages = [];
        foreach ($caughtExceptions as $index => $caughtException) {
            $messages[sprintf('CaughtException (%s)', $index)] = $caughtException->toMessage();
            $lastException = $caughtException->getException();
        }

        // neos ui logging
        $nodeTemplateError = new Error();
        $nodeTemplateError->setMessage($initialMessageInCaseOfException);

        $this->feedbackCollection->add(
            $nodeTemplateError
        );

        foreach ($messages as $message) {
            $error = new Error();
            $error->setMessage($message);
            $this->feedbackCollection->add(
                $error
            );
        }

        $exception = new TemplatePartiallyAppliedException($initialMessageInCaseOfException, 1685880697387, $lastException);
        $messageWithReference = $this->throwableStorage->logThrowable($exception, $messages);
        $this->logger->warning($messageWithReference, LogEnvironment::fromMethodName(__METHOD__));
    }
}
