<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\Template;

use Neos\Flow\Annotations as Flow;

/**
 * The root of a template (which is not allowed to have a "name" and a "type" unlike {@see Template}
 *
 * @Flow\Proxy(false)
 */
class RootTemplate implements \JsonSerializable
{
    /**
     * @var array<string, mixed>
     */
    private array $properties;

    private SubtreeTags $tags;

    private Templates $childNodes;

    /**
     * @internal
     * @param array<string, mixed> $properties
     */
    public function __construct(array $properties, SubtreeTags $tags, Templates $childNodes)
    {
        $this->properties = $properties;
        $this->tags = $tags;
        $this->childNodes = $childNodes;
    }

    public static function empty(): self
    {
        return new RootTemplate([], SubtreeTags::createEmpty(), Templates::empty());
    }

    /**
     * @return array<string, string>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getTags(): SubtreeTags
    {
        return $this->tags;
    }

    public function getChildNodes(): Templates
    {
        return $this->childNodes;
    }

    public function jsonSerialize(): array
    {
        return [
            'properties' => $this->properties,
            'tags' => $this->tags,
            'childNodes' => $this->childNodes
        ];
    }
}
