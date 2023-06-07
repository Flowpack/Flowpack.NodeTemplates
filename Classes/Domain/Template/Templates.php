<?php

namespace Flowpack\NodeTemplates\Domain\Template;

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
        if (count($this->items) > 1) {
            throw new \BadMethodCallException('Templates cannot be transformed to RootTemplate because it holds multiple Templates.', 1685866910655);
        }
        foreach ($this->items as $first) {
            return new RootTemplate(
                $first->getDisabled(),
                $first->getProperties(),
                $first->getChildNodes()
            );
        }
        return RootTemplate::empty();
    }

    public function jsonSerialize()
    {
        return $this->items;
    }
}
