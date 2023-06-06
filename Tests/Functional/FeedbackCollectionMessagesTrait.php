<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Neos\Ui\Domain\Model\Feedback\AbstractMessageFeedback;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Neos\Neos\Ui\Domain\Model\FeedbackInterface;
use Neos\Utility\ObjectAccess;

trait FeedbackCollectionMessagesTrait
{
    private function getMessagesOfFeedbackCollection(): array
    {
        /** @var FeedbackInterface[] $allFeedbacks */
        $allFeedbacks = ObjectAccess::getProperty($this->objectManager->get(FeedbackCollection::class), 'feedbacks', true);

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
