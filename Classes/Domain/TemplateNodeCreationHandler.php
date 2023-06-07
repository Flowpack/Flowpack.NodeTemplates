<?php

namespace Flowpack\NodeTemplates\Domain;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\ExceptionHandlingStrategy;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\TemplatePartiallyAppliedException;
use Flowpack\NodeTemplates\Domain\TemplateConfiguration\TemplateConfigurationProcessor;
use Flowpack\NodeTemplates\Domain\NodeCreation\NodeCreationService;
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
     * @var TemplateConfigurationProcessor
     * @Flow\Inject
     */
    protected $templateFactory;

    /**
     * @var NodeCreationService
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
     * @Flow\InjectConfiguration(package="Flowpack.NodeTemplates", path="exceptionHandlingStrategy")
     */
    public array $exceptionHandlingStrategyConfiguration;

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

        $exceptionHandlingStrategy = ExceptionHandlingStrategy::fromConfiguration($this->exceptionHandlingStrategyConfiguration);

        $caughtExceptions = CaughtExceptions::create();
        try {
            $template = $this->templateFactory->processTemplateConfiguration($templateConfiguration, $evaluationContext, $caughtExceptions);
            if (!$caughtExceptions->hasExceptions() || $exceptionHandlingStrategy->continueWithPartiallyEvaluatedTemplate()) {
                $this->contentRepositoryTemplateHandler->apply($template, $node, $caughtExceptions);
            }
        } finally {
            $this->handleCaughtExceptionsForNode($caughtExceptions, $node, $exceptionHandlingStrategy);
        }
    }

    private function handleCaughtExceptionsForNode(CaughtExceptions $caughtExceptions, NodeInterface $node, ExceptionHandlingStrategy $exceptionHandlingStrategy): void
    {
        if (!$caughtExceptions->hasExceptions()) {
            return;
        }

        $initialMessageInCaseOfException = $exceptionHandlingStrategy->continueWithPartiallyEvaluatedTemplate()
            ? sprintf('Template for "%s" only partially applied. Please check the newly created nodes beneath %s.', $node->getNodeType()->getLabel(), (string)$node)
            : sprintf('Template for "%s" was not applied. Only %s was created.', $node->getNodeType()->getLabel(), (string)$node);

        $firstException = null;
        $messages = [];
        foreach ($caughtExceptions as $index => $caughtException) {
            $messages[sprintf('CaughtException (%s)', $index)] = $caughtException->toMessage();
            $firstException = $firstException ?? $caughtException->getException();
        }

        // log exception
        $exception = new TemplatePartiallyAppliedException($initialMessageInCaseOfException, 1685880697387, $firstException);
        $messageWithReference = $this->throwableStorage->logThrowable($exception, $messages);
        $this->logger->warning($messageWithReference, LogEnvironment::fromMethodName(__METHOD__));

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
    }
}
