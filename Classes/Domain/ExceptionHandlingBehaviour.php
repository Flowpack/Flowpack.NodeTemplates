<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ExceptionHandlingBehaviour
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromConfiguration($configuration): self
    {
        if ($configuration !== 'APPLY_PARTIAL_TEMPLATE' && $configuration !== 'DONT_APPLY_PARTIAL_TEMPLATE') {
            throw new \DomainException(sprintf('ExceptionHandlingBehaviour only allows "APPLY_PARTIAL_TEMPLATE" or "ABORT_PARTIAL_TEMPLATE". Got: "%s".', $configuration), 1685902298628);
        }
        return new self($configuration);
    }

    public function shouldApplyPartialTemplate(): bool
    {
        return $this->value === 'APPLY_PARTIAL_TEMPLATE';
    }
}
