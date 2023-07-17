<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Collection of {@see NodeMutator}
 *
 * To apply the mutators: {@see self::executeWithStartingNode()}
 *
 * @Flow\Proxy(false)
 */
class NodeMutatorCollection
{
    private array $items;

    private function __construct(
        NodeMutator ...$items
    ) {
        $this->items = $items;
    }

    public static function from(NodeMutator ...$items): self
    {
        return new self(...$items);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function append(NodeMutator ...$items): self
    {
        return new self(...$this->items, ...$items);
    }

    public function merge(self $other): self
    {
        return new self(...$this->items, ...$other->items);
    }

    /**
     * Applies all child operations on the initial node pointer
     *
     * @param NodeInterface $nodePointer being the current node for the first operation
     */
    public function executeWithStartingNode(NodeInterface $nodePointer): void
    {
        foreach ($this->items as $mutator) {
            $nodePointer = $mutator->executeWithNodePointer($nodePointer);
        }
    }
}
