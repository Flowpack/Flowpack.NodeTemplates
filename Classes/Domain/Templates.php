<?php

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class Templates implements \IteratorAggregate, \JsonSerializable
{
    /** @var array<int|string, Template> */
    private array $items;

    public function __construct(
        Template ...$items
    ) {
        $this->items = $items;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return \Traversable<int|string, Template>|Template[]
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function withAdded(Template $template): self
    {
        return new  self(...$this->items, ...[$template]);
    }

    public function merge(Templates $other): self
    {
        return new self(...$this->items, ...$other->items);
    }

    public function toRootTemplate(): RootTemplate
    {
        assert(count($this->items) <= 1);
        foreach ($this->items as $first) {
            return new RootTemplate(
                $first->getProperties(),
                $first->getChildNodes()
            );
        }
        return new RootTemplate([], new Templates());
    }

    public function jsonSerialize()
    {
        return $this->items;
    }
}
