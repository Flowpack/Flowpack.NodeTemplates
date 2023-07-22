<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class ProcessingErrors implements \IteratorAggregate
{
    /** @var array<int, ProcessingError> */
    private array $exceptions = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function hasExceptions(): bool
    {
        return $this->exceptions !== [];
    }

    public function add(ProcessingError $exception): void
    {
        $this->exceptions[] = $exception;
    }

    public function first(): ?ProcessingError
    {
        return $this->exceptions[0] ?? null;
    }

    /**
     * @return \Traversable<int, ProcessingError>|ProcessingError[]
     */
    public function getIterator()
    {
        yield from $this->exceptions;
    }
}
