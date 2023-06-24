<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final readonly class ToBeCreatedNode
{
    private function __construct(
        public ContentStreamId $contentStreamId,
        public OriginDimensionSpacePoint $originDimensionSpacePoint,
        public NodeAggregateId $nodeAggregateId,
        public NodeType $nodeType,
        private ?NodeName $tetheredNodeName,
        private ?NodeType $tetheredParentNodeType,
    ) {
        if ($this->tetheredNodeName !== null) {
            assert($this->tetheredParentNodeType !== null);
        }
    }

    public static function fromRegular(
        ContentStreamId $contentStreamId,
        OriginDimensionSpacePoint $originDimensionSpacePoint,
        NodeAggregateId $nodeAggregateId,
        NodeType $nodeType
    ): self {
        return new self(
            $contentStreamId,
            $originDimensionSpacePoint,
            $nodeAggregateId,
            $nodeType,
            null,
            null
        );
    }

    public function forTetheredChildNode(NodeName $nodeName, NodeAggregateId $nodeAggregateId): self
    {
        // `getTypeOfAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $this->nodeType->getAutoCreatedChildNodes();
        $childNodeType = $parentNodesAutoCreatedChildNodes[$nodeName->value] ?? null;
        if (!$childNodeType instanceof NodeType) {
            throw new \InvalidArgumentException('forTetheredChildNode only works for tethered nodes.');
        }
        return new self(
            $this->contentStreamId,
            $this->originDimensionSpacePoint,
            $nodeAggregateId,
            $childNodeType,
            $nodeName,
            $this->nodeType
        );
    }

    public function forRegularChildNode(NodeType $nodeType, NodeAggregateId $nodeAggregateId): self
    {
        return new self(
            $this->contentStreamId,
            $this->originDimensionSpacePoint,
            $nodeAggregateId,
            $nodeType,
            null,
            null
        );
    }

    /**
     * @throws NodeConstraintException
     */
    public function requireConstraintsImposedByAncestorsAreMet(NodeType $childNodeType): void
    {
        if ($this->tetheredNodeName) {
            self::requireNodeTypeConstraintsImposedByGrandparentToBeMet($this->tetheredParentNodeType, $this->tetheredNodeName, $childNodeType);
        } else {
            self::requireNodeTypeConstraintsImposedByParentToBeMet($this->nodeType, $childNodeType);
        }
    }

    public function getNodeType(): NodeType
    {
        return $this->nodeType;
    }

    private static function requireNodeTypeConstraintsImposedByParentToBeMet(NodeType $parentNodeType, NodeType $nodeType): void
    {
        if (!$parentNodeType->allowsChildNodeType($nodeType)) {
            throw new NodeConstraintException(
                sprintf(
                    'Node type "%s" is not allowed for child nodes of type %s',
                    $nodeType->name->value,
                    $parentNodeType->name->value
                ),
                1686417627173
            );
        }
    }

    private static function requireNodeTypeConstraintsImposedByGrandparentToBeMet(NodeType $grandParentNodeType, NodeName $nodeName, NodeType $nodeType): void
    {
        if (!$grandParentNodeType->allowsGrandchildNodeType($nodeName->value, $nodeType)) {
            throw new NodeConstraintException(
                sprintf(
                    'Node type "%s" is not allowed below tethered child nodes "%s" of nodes of type "%s"',
                    $nodeType->name->value,
                    $nodeName->value,
                    $grandParentNodeType->name->value
                ),
                1687541480146
            );
        }
    }
}
