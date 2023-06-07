<?php

namespace Flowpack\NodeTemplates\Domain\ExceptionHandling;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ExceptionHandlingStrategy
{
    private bool $continueWithPartiallyEvaluatedTemplate;

    public function __construct(bool $continueWithPartiallyEvaluatedTemplate)
    {
        $this->continueWithPartiallyEvaluatedTemplate = $continueWithPartiallyEvaluatedTemplate;
    }

    public static function fromConfiguration(array $configuration): self
    {
        if (
            array_diff(['continueWithPartiallyEvaluatedTemplate'], array_keys($configuration)) !== []
            || !is_bool($configuration['continueWithPartiallyEvaluatedTemplate'])
        ) {
            throw new \DomainException(sprintf('ExceptionHandlingStrategy configuration invalid. Only option "continueWithPartiallyEvaluatedTemplate: boolean" allowed. Got: `%s`', json_encode($configuration)), 1686031855183);
        }
        return new self($configuration['continueWithPartiallyEvaluatedTemplate']);
    }

    public function continueWithPartiallyEvaluatedTemplate(): bool
    {
        return $this->continueWithPartiallyEvaluatedTemplate;
    }
}
