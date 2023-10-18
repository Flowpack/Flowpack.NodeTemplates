<?php

namespace Flowpack\NodeTemplates\Domain\ErrorHandling;

/** @implements \IteratorAggregate<int, ProcessingError> */
class ProcessingErrors implements \IteratorAggregate
{
    /** @var array<int, ProcessingError> */
    private array $errors = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function hasError(): bool
    {
        return $this->errors !== [];
    }

    public function add(ProcessingError $error): void
    {
        $this->errors[] = $error;
    }

    public function first(): ?ProcessingError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * @return \Traversable<int, ProcessingError>|ProcessingError[]
     */
    public function getIterator(): \Traversable
    {
        yield from $this->errors;
    }
}
