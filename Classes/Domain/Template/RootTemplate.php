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
    private ?bool $disabled;

    /**
     * @var array<string, mixed>
     */
    private array $properties;

    private Templates $childNodes;

    /**
     * @internal
     * @param array<string, mixed> $properties
     */
    public function __construct(?bool $disabled, array $properties, Templates $childNodes)
    {
        $this->disabled = $disabled;
        $this->properties = $properties;
        $this->childNodes = $childNodes;
    }

    public static function empty(): self
    {
        return new RootTemplate(null, [], Templates::empty());
    }

    public function getDisabled(): ?bool
    {
        return $this->disabled;
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
            'disabled' => $this->disabled,
            'properties' => $this->properties,
            'childNodes' => $this->childNodes
        ];
    }
}
