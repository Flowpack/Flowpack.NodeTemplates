<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Infrastructure;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\Context;

/**
 * The reference type value object as declared in a NodeType
 */
final class ReferenceType
{
    public const TYPE_REFERENCE = 'reference';
    public const TYPE_REFERENCES = 'references';

    private string $value;

    private function __construct(
        string $value
    ) {
        $this->value = $value;
    }

    public static function fromPropertyOfNodeType(
        string $propertyName,
        NodeType $nodeType
    ): self {
        $declaration = $nodeType->getPropertyType($propertyName);
        if ($declaration === 'reference') {
            return self::reference();
        }
        if ($declaration === 'references') {
            return self::references();
        }
        throw new \DomainException(
            sprintf(
                'Given property "%s" is not declared as "reference" in node type "%s" and must be treated as such.',
                $propertyName,
                $nodeType->getName()
            ),
            1685964955964
        );
    }

    public static function reference(): self
    {
        return new self(self::TYPE_REFERENCE);
    }

    public static function references(): self
    {
        return new self(self::TYPE_REFERENCES);
    }

    public function isReference(): bool
    {
        return $this->value === self::TYPE_REFERENCE;
    }

    public function isReferences(): bool
    {
        return $this->value === self::TYPE_REFERENCES;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isMatchedBy($propertyValue, Context $subgraph): bool
    {
        if ($propertyValue === null) {
            return true;
        }
        $nodeAggregatesOrIds = $this->isReference() ? [$propertyValue] : $propertyValue;
        if (is_array($nodeAggregatesOrIds) === false) {
            return false;
        }
        foreach ($nodeAggregatesOrIds as $singleNodeAggregateOrId) {
            if ($singleNodeAggregateOrId instanceof NodeInterface) {
                continue;
            }
            if (is_string($singleNodeAggregateOrId) && $subgraph->getNodeByIdentifier($singleNodeAggregateOrId) instanceof NodeInterface) {
                continue;
            }
            return false;
        }
        return true;
    }
}
