<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Neos\Ui\Domain\Model\Feedback\AbstractMessageFeedback;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackInterface;
use Neos\Utility\ObjectAccess;

trait FeedbackCollectionMessagesTrait
{
    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract protected function getObject(string $className): object;

    /**
     * @return array<int,mixed>
     */
    private function getMessagesOfFeedbackCollection(): array
    {
        /** @var FeedbackInterface[] $allFeedbacks */
        $allFeedbacks = ObjectAccess::getProperty($this->getObject(FeedbackCollection::class), 'feedbacks', true);

        /** @var AbstractMessageFeedback[] $allFeedbacks */
        $messages = [];
        foreach ($allFeedbacks as $feedback) {
            if ($feedback instanceof AbstractMessageFeedback) {
                $messages[] = $feedback->serializePayload($this->createStub(ControllerContext::class));
            }
        }
        return $messages;
    }
}
