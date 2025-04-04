<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Core\NodeType\NodeType;
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
        string $referenceName,
        NodeType $nodeType
    ): self {
        if (!$nodeType->hasReference($referenceName)) {
            throw new \DomainException(
                sprintf(
                    'Given reference "%s" is not declared in node type "%s".',
                    $referenceName,
                    $nodeType->name->value
                ),
                1685964955964
            );
        }

        $maxItems = $nodeType->getReferences()[$referenceName]['constraints']['maxItems'] ?? null;
        if ($maxItems === 1) {
            return self::reference();
        }
        return self::references();
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

    public function toNodeAggregateId(mixed $referenceValue): ?NodeAggregateId
    {
        if ($referenceValue === null) {
            return null;
        }
        if ($referenceValue instanceof Node) {
            return $referenceValue->aggregateId;
        }
        try {
            return NodeAggregateId::fromString($referenceValue);
        } catch (\Throwable $exception) {
            throw new InvalidReferenceException(
                sprintf(
                    'Invalid reference value. Value `%s` is not a valid node or node identifier.',
                    json_encode($referenceValue)
                ),
                1687632177555
            );
        }
    }

    public function toNodeAggregateIds(mixed $referenceValue): NodeAggregateIds
    {
        if ($referenceValue === null) {
            return NodeAggregateIds::createEmpty();
        }

        if (is_array($referenceValue) === false) {
            throw new InvalidReferenceException(
                sprintf(
                    'Invalid reference value. Value `%s` is not a valid list of nodes or node identifiers.',
                    json_encode($referenceValue)
                ),
                1685958176560
            );
        }

        $nodeAggregateIds = [];
        foreach ($referenceValue as $singleNodeAggregateOrId) {
            if ($singleNodeAggregateOrId instanceof Node) {
                $nodeAggregateIds[] = $singleNodeAggregateOrId->aggregateId;
                continue;
            }
            try {
                $nodeAggregateIds[] = NodeAggregateId::fromString($singleNodeAggregateOrId);
            } catch (\Throwable $exception) {
                throw new InvalidReferenceException(
                    sprintf(
                        'Invalid reference value. Value `%s` is not a valid list of nodes or node identifiers.',
                        json_encode($referenceValue)
                    ),
                    1685958176560
                );
            }
        }
        return NodeAggregateIds::fromArray($nodeAggregateIds);
    }
}
