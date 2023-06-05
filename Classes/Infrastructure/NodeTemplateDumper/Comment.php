<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Infrastructure\NodeTemplateDumper;

use Neos\Flow\Annotations as Flow;

/**
 * Wrapper around a comment render function
 * {@see Comments}
 *
 * @Flow\Proxy(false)
 */
class Comment
{
    /**
     * @psalm-var \Closure(string $indentation, string $propertyName): string $renderFunction
     */
    private \Closure $renderFunction;

    /**
     * @psalm-param \Closure(string $indentation, string $propertyName): string $renderFunction
     */
    private function __construct(\Closure $renderFunction)
    {
        $this->renderFunction = $renderFunction;
    }

    /**
     * @psalm-param \Closure(string $indentation, string $propertyName): string $renderFunction
     */
    public static function fromRenderer($renderFunction): self
    {
        return new self($renderFunction);
    }

    public function toYamlComment(string $indentation, string $propertyName): string
    {
        return ($this->renderFunction)($indentation, $propertyName);
    }
}
