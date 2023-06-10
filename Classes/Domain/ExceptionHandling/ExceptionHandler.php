<?php

namespace Flowpack\NodeTemplates\Domain\ExceptionHandling;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Psr\Log\LoggerInterface;

class ExceptionHandler
{
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
     * @var ExceptionHandlingConfiguration
     * @Flow\Inject
     */
    protected $configuration;

    public function handleAfterTemplateConfigurationProcessing(CaughtExceptions $caughtExceptions, NodeType $nodeType, NodeAggregateId $nodeAggregateId): void
    {
        if (!$caughtExceptions->hasExceptions()) {
            return;
        }

        if (!$this->configuration->shouldStopOnExceptionAfterTemplateConfigurationProcessing()) {
            return;
        }

        $templateNotCreatedException = new TemplateNotCreatedException(
            sprintf('Template for "%s" was not applied. Only %s was created.', $nodeType->getLabel(), $nodeAggregateId->value),
            1686135532992,
            $caughtExceptions->first()->getException(),
        );

        $this->logCaughtExceptions($caughtExceptions, $templateNotCreatedException);

        throw $templateNotCreatedException;
    }

    public function handleAfterNodeCreation(CaughtExceptions $caughtExceptions, NodeType $nodeType, NodeAggregateId $nodeAggregateId): void
    {
        if (!$caughtExceptions->hasExceptions()) {
            return;
        }

        $templatePartiallyCreatedException = new TemplatePartiallyCreatedException(
            sprintf('Template for "%s" only partially applied. Please check the newly created nodes beneath %s.', $nodeType->getLabel(), $nodeAggregateId->value),
            1686135564160,
            $caughtExceptions->first()->getException(),
        );

        $this->logCaughtExceptions($caughtExceptions, $templatePartiallyCreatedException);

        throw $templatePartiallyCreatedException;
    }

    /**
     * @param TemplateNotCreatedException|TemplatePartiallyCreatedException $templateCreationException
     */
    private function logCaughtExceptions(CaughtExceptions $caughtExceptions, \DomainException $templateCreationException): void
    {
        $messages = [];
        foreach ($caughtExceptions as $index => $caughtException) {
            $messages[sprintf('CaughtException (%s)', $index)] = $caughtException->toMessage();
        }

        // log exception
        $messageWithReference = $this->throwableStorage->logThrowable($templateCreationException, $messages);
        $this->logger->warning($messageWithReference, LogEnvironment::fromMethodName(__METHOD__));

        // neos ui logging
        $nodeTemplateError = new Error();
        $nodeTemplateError->setMessage($templateCreationException->getMessage());

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
