<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
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

    public function toNodeAggregateId($referenceValue): ?NodeAggregateIdentifier
    {
        if ($referenceValue === null) {
            return null;
        }
        if ($referenceValue instanceof NodeInterface) {
            return NodeAggregateIdentifier::fromString($referenceValue->getIdentifier());
        }
        try {
            return NodeAggregateIdentifier::fromString($referenceValue);
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

    /**
     * @param mixed $referenceValue
     * @return array<int, NodeAggregateIdentifier>
     */
    public function toNodeAggregateIds($referenceValue): array
    {
        if ($referenceValue === null) {
            return [];
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
            if ($singleNodeAggregateOrId instanceof NodeInterface) {
                $nodeAggregateIds[] = NodeAggregateIdentifier::fromString($singleNodeAggregateOrId->getIdentifier());
                continue;
            }
            try {
                $nodeAggregateIds[] = NodeAggregateIdentifier::fromString($singleNodeAggregateOrId);
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
        return $nodeAggregateIds;
    }
}
