<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Psr\Log\LoggerInterface;

class ProcessingErrorHandler
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
     * @var ErrorHandlingConfiguration
     * @Flow\Inject
     */
    protected $configuration;

    /**
     * @return bool if to continue or abort
     */
    public function handleAfterTemplateConfigurationProcessing(ProcessingErrors $processingErrors, NodeInterface $node): bool
    {
        if (!$processingErrors->hasError()) {
            return true;
        }

        if (!$this->configuration->shouldStopOnExceptionAfterTemplateConfigurationProcessing()) {
            return true;
        }

        $templateNotCreatedException = new TemplateNotCreatedException(
            sprintf('Template for "%s" was not applied. Only %s was created.', $node->getNodeType()->getLabel(), (string)$node),
            1686135532992,
            $processingErrors->first()->getException(),
        );

        $this->logProcessingErrors($processingErrors, $templateNotCreatedException);

        return false;
    }

    /**
     * @return bool if to continue or abort
     */
    public function handleAfterNodeCreation(ProcessingErrors $processingErrors, NodeInterface $node): bool
    {
        if (!$processingErrors->hasError()) {
            return true;
        }

        $templatePartiallyCreatedException = new TemplatePartiallyCreatedException(
            sprintf('Template for "%s" only partially applied. Please check the newly created nodes beneath %s.', $node->getNodeType()->getLabel(), (string)$node),
            1686135564160,
            $processingErrors->first()->getException(),
        );

        $this->logProcessingErrors($processingErrors, $templatePartiallyCreatedException);

        // TODO add setting to abort here in case of previous processing errors
        return true;
    }

    /**
     * @param TemplateNotCreatedException|TemplatePartiallyCreatedException $templateCreationException
     */
    private function logProcessingErrors(ProcessingErrors $processingErrors, \DomainException $templateCreationException): void
    {
        $messages = [];
        foreach ($processingErrors as $index => $processingError) {
            $messages[sprintf('ProcessingError (%s)', $index)] = $processingError->toMessage();
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
