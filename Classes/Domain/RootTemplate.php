<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain;

use Neos\Flow\Annotations as Flow;

/**
 * The root of a template (which is not allowed to have a "name" and a "type" unlike {@see Template}
 *
 * @Flow\Proxy(false)
 */
class RootTemplate implements \JsonSerializable
{
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
    public function __construct(?bool $hidden, array $properties, Templates $childNodes)
    {
        $this->hidden = $hidden;
        $this->properties = $properties;
        $this->childNodes = $childNodes;
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
            'hidden' => $this->hidden,
            'properties' => $this->properties,
            'childNodes' => $this->childNodes
        ];
    }
}
