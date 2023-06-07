<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\Template;

use Flowpack\NodeTemplates\Domain\Template\Template;
use Flowpack\NodeTemplates\Domain\Template\Templates;
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

    private Templates $childNodes;

    /**
     * @internal
     * @param array<string, mixed> $properties
     */
    public function __construct(array $properties, Templates $childNodes)
    {
        $this->properties = $properties;
        $this->childNodes = $childNodes;
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
            'properties' => $this->properties,
            'childNodes' => $this->childNodes
        ];
    }
}
