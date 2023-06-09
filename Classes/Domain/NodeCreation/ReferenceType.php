<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\Flow\Annotations as Flow;

/**
 * The reference type value object as declared in a NodeType
 *
 * @Flow\Proxy(false)
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

    /**
     * @param mixed $referenceValue
     * @param ContentSubgraphInterface $subgraphForResolving
     * @throws \RuntimeException in case the $referenceValue could not be resolved to a node
     */
    public function resolveNodeAggregateIds(mixed $referenceValue, ContentSubgraphInterface $subgraphForResolving): NodeAggregateIds
    {
        if ($referenceValue === null) {
            return NodeAggregateIds::createEmpty();
        }

        $becauseOfInvalidValue = fn () => throw new \RuntimeException(
            sprintf(
                'Reference could not be set, because node reference(s) %s cannot be resolved.',
                json_encode($referenceValue)
            ),
            1685958176560
        );

        $nodeAggregatesOrIds = $this->isReference() ? [$referenceValue] : $referenceValue;
        if (is_array($nodeAggregatesOrIds) === false) {
            throw $becauseOfInvalidValue();
        }

        $nodeAggregateIds = [];

        foreach ($nodeAggregatesOrIds as $singleNodeAggregateOrId) {
            if ($singleNodeAggregateOrId instanceof Node) {
                $nodeAggregateIds[] = $singleNodeAggregateOrId->nodeAggregateId;
                continue;
            }
            try {
                $singleNodeAggregateId = is_string($singleNodeAggregateOrId) ? NodeAggregateId::fromString($singleNodeAggregateOrId) : $singleNodeAggregateOrId;
            } catch (\Exception) {
                throw $becauseOfInvalidValue();
            }
            if ($singleNodeAggregateId instanceof NodeAggregateId && $subgraphForResolving->findNodeById($singleNodeAggregateId)) {
                $nodeAggregateIds[] = $singleNodeAggregateId;
                continue;
            }
            throw $becauseOfInvalidValue();
        }
        return NodeAggregateIds::fromArray($nodeAggregateIds);
    }
}
