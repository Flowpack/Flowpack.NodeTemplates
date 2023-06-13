<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class NodeMutators
{
    private array $items;

    private function __construct(
        NodeMutator ...$items
    ) {
        $this->items = $items;
    }

    public static function create(NodeMutator ...$items): self
    {
        return new self(...$items);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withNodeMutators(NodeMutator ...$items): self
    {
        return new self(...$this->items, ...$items);
    }

    public function apply(NodeInterface $node): void
    {
        foreach ($this->items as $mutator) {
            $node = $mutator->apply($node);
        }
    }

    public function merge(self $other): self
    {
        return new self(...$this->items, ...$other->items);
    }
}
