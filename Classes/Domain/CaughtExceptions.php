<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

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

    public function hasExceptions(): bool
    {
        return $this->exceptions !== [];
    }

    public function add(CaughtException $exception): void
    {
        $this->exceptions[] = $exception;
    }

    /**
     * @return \Traversable<int, CaughtException>|CaughtException[]
     */
    public function getIterator()
    {
        yield from $this->exceptions;
    }
}
