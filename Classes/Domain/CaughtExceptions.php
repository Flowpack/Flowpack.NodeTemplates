<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class CaughtExceptions implements \IteratorAggregate
{
    /** @var array<int, \Exception> */
    private array $exceptions = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function add(\Exception $exception): void
    {
        $this->exceptions[] = $exception;
    }

    public function getIterator()
    {
        yield from $this->exceptions;
    }
}
