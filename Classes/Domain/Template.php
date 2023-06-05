<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations as Flow;

/** @Flow\Proxy(false) */
class Template implements \JsonSerializable
{
    private ?NodeTypeName $type;

    private ?NodeName $name;

    private ?bool $hidden;

    /**
     * @var array<string, mixed>
     */
    private array $properties;

    private Templates $childNodes;

    /**
     * @internal
     * @param array<string, mixed> $properties
     */
    public function __construct(?NodeTypeName $type, ?NodeName $name, ?bool $hidden, array $properties, Templates $childNodes)
    {
        $this->type = $type;
        $this->name = $name;
        $this->hidden = $hidden;
        $this->properties = $properties;
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

    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    /**
     * @return array<string, string>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getChildNodes(): Templates
    {
        return $this->childNodes;
    }

    public function jsonSerialize()
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'hidden' => $this->hidden,
            'properties' => $this->properties,
            'childNodes' => $this->childNodes
        ];
    }
}
