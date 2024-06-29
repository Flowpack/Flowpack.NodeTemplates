<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\Template;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/**
 * Evaluated template from the yaml configuration
 *
 * @Flow\Proxy(false)
 */
class Template implements \JsonSerializable
{
    private ?NodeTypeName $type;

    private ?NodeName $name;

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
    public function __construct(?NodeTypeName $type, ?NodeName $name, array $properties, SubtreeTags $tags, Templates $childNodes)
    {
        $this->type = $type;
        $this->name = $name;
        $this->properties = $properties;
        $this->tags = $tags;
        $this->childNodes = $childNodes;
    }

    public function getType(): ?NodeTypeName
    {
        return $this->type;
    }

    public function getName(): ?NodeName
    {
        return $this->name;
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
            'type' => $this->type,
            'name' => $this->name,
            'properties' => $this->properties,
            'tags' => $this->tags,
            'childNodes' => $this->childNodes
        ];
    }
}
