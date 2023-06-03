<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;

/** @Flow\Proxy(false) */
class CaughtExceptions implements \IteratorAggregate
{
    /** @var array<int, CaughtException> */
    private array $exceptions = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function add(CaughtException $exception): void
    {
        $this->exceptions[] = $exception;
    }

    public function serializeIntoFeedbackCollection(FeedbackCollection $feedbackCollection, NodeInterface $node): void
    {
        if ($this->exceptions === []) {
            return;
        }
        $nodeTemplateError = new Error();
        $nodeTemplateError->setMessage(sprintf('Template for "%s" only partially applied. Please check the newly created nodes.', $node->getNodeType()->getLabel()));

        $feedbackCollection->add(
            $nodeTemplateError
        );

        foreach ($this->exceptions as $caughtException) {
            $feedbackCollection->add(
                $caughtException->toMessageFeedback()
            );
        }
    }

    /**
     * @return CaughtException[]
     */
    public function getIterator()
    {
        yield from $this->exceptions;
    }
}
