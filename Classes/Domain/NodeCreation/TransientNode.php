<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\Flow\Annotations as Flow;

/**
 * Model about a non materialized node.
 *
 * The "to be created" node might not yet be available - and may never be.
 *
 * The transient node makes it possible, to still be able to enforce constraints {@see self::requireNodeTypeConstraintsImposedByGrandparentToBeMet()}
 * and get information {@see self::$properties} about a node.
 *
 * For example the transient node can be passed as fictional $parentNode.
 * To create child transient nodes of the $parentNode use {@see self::forRegularChildNode()} and {@see self::forTetheredChildNode()}
 *
 * An initial transient node can be created with {@see self::forRegular}
 *
 * @Flow\Proxy(false)
 */
final readonly class TransientNode
{
    public array $properties;

    public array $references;

    private function __construct(
        public NodeAggregateId $nodeAggregateId,
        public ContentStreamId $contentStreamId,
        public OriginDimensionSpacePoint $originDimensionSpacePoint,
        public NodeType $nodeType,
        public NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds,
        private ?NodeName $tetheredNodeName,
        private ?NodeType $tetheredParentNodeType,
        public NodeTypeManager $nodeTypeManager,
        public ContentSubgraphInterface $subgraph,
        array $rawProperties
    ) {
        if ($this->tetheredNodeName !== null) {
            assert($this->tetheredParentNodeType !== null);
        }

        // split properties and references by type declaration
        $properties = [];
        $references = [];
        foreach ($rawProperties as $propertyName => $propertyValue) {
            $declaration = $this->nodeType->getPropertyType($propertyName);
            if ($declaration === 'reference' || $declaration === 'references') {
                $references[$propertyName] = $propertyValue;
                continue;
            }
            $properties[$propertyName] = $propertyValue;
        }
        $this->properties = $properties;
        $this->references = $references;
    }

    public static function forRegular(
        NodeAggregateId $nodeAggregateId,
        ContentStreamId $contentStreamId,
        OriginDimensionSpacePoint $originDimensionSpacePoint,
        NodeType $nodeType,
        NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds,
        NodeTypeManager $nodeTypeManager,
        ContentSubgraphInterface $subgraph,
        array $rawProperties
    ): self {
        return new self(
            $nodeAggregateId,
            $contentStreamId,
            $originDimensionSpacePoint,
            $nodeType,
            $tetheredNodeAggregateIds,
            null,
            null,
            $nodeTypeManager,
            $subgraph,
            $rawProperties
        );
    }

    public function forTetheredChildNode(NodeName $nodeName, array $rawProperties): self
    {
        $nodeAggregateId = $this->tetheredNodeAggregateIds->getNodeAggregateId(NodePath::fromNodeNames($nodeName));

        if (!$nodeAggregateId || !$this->nodeType->hasTetheredNode($nodeName)) {
            throw new \InvalidArgumentException('forTetheredChildNode only works for tethered nodes.');
        }

        $childNodeType = $this->nodeTypeManager->getTypeOfTetheredNode($this->nodeType, $nodeName);

        $descendantTetheredNodeAggregateIds = NodeAggregateIdsByNodePaths::createEmpty();
        foreach ($this->tetheredNodeAggregateIds->getNodeAggregateIds() as $stringNodePath => $descendantNodeAggregateId) {
            $nodePath = NodePath::fromString($stringNodePath);
            $pathParts = $nodePath->getParts();
            $firstPart = array_pop($pathParts);
            if ($firstPart?->equals($nodeName) && count($pathParts)) {
                $descendantTetheredNodeAggregateIds = $descendantTetheredNodeAggregateIds->add(
                    NodePath::fromNodeNames(...$pathParts),
                    $descendantNodeAggregateId
                );
            }
        }

        return new self(
            $nodeAggregateId,
            $this->contentStreamId,
            $this->originDimensionSpacePoint,
            $childNodeType,
            $descendantTetheredNodeAggregateIds,
            $nodeName,
            $this->nodeType,
            $this->nodeTypeManager,
            $this->subgraph,
            $rawProperties
        );
    }

    public function forRegularChildNode(NodeAggregateId $nodeAggregateId, NodeType $nodeType, array $rawProperties): self
    {
        $tetheredNodeAggregateIds = NodeAggregateIdsByNodePaths::createForNodeType($nodeType->name, $this->nodeTypeManager);
        return new self(
            $nodeAggregateId,
            $this->contentStreamId,
            $this->originDimensionSpacePoint,
            $nodeType,
            $tetheredNodeAggregateIds,
            null,
            null,
            $this->nodeTypeManager,
            $this->subgraph,
            $rawProperties
        );
    }

    /**
     * @throws NodeConstraintException
     */
    public function requireConstraintsImposedByAncestorsToBeMet(NodeType $childNodeType): void
    {
        if ($this->tetheredNodeName) {
            $this->requireNodeTypeConstraintsImposedByGrandparentToBeMet($this->tetheredParentNodeType, $this->tetheredNodeName, $childNodeType);
        } else {
            self::requireNodeTypeConstraintsImposedByParentToBeMet($this->nodeType, $childNodeType);
        }
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

    private function requireNodeTypeConstraintsImposedByGrandparentToBeMet(NodeType $grandParentNodeType, NodeName $nodeName, NodeType $nodeType): void
    {
        if (!$this->nodeTypeManager->isNodeTypeAllowedAsChildToTetheredNode($grandParentNodeType, $nodeName, $nodeType)) {
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
