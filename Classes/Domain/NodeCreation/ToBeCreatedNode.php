<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class ToBeCreatedNode
{
    private NodeType $nodeType;

    /** @var \Closure(NodeType $nodeType): void */
    private \Closure $requireConstraintsImposedByAncestorsAreMet;

    private function __construct(NodeType $nodeType, \Closure $requireConstraintsImposedByAncestorsAreMet)
    {
        $this->nodeType = $nodeType;
        $this->requireConstraintsImposedByAncestorsAreMet = $requireConstraintsImposedByAncestorsAreMet;
    }

    public static function fromRegular(NodeType $nodeType): self
    {
        $parentNodeType = $nodeType;
        $requireConstraintsImposedByAncestorsAreMet = function (NodeType $nodeType) use ($parentNodeType) : void {
            self::requireNodeTypeConstraintsImposedByParentToBeMet($parentNodeType, $nodeType);
        };
        return new self($nodeType, $requireConstraintsImposedByAncestorsAreMet);
    }

    public function forTetheredChildNode(NodeName $nodeName): self
    {
        $parentNodeType = $this->nodeType;
        // `getTypeOfAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNodeType->getAutoCreatedChildNodes();
        $childNodeType = $parentNodesAutoCreatedChildNodes[$nodeName->__toString()] ?? null;
        if (!$childNodeType instanceof NodeType) {
            throw new \InvalidArgumentException('forTetheredChildNode only works for tethered nodes.');
        }
        $requireConstraintsImposedByAncestorsAreMet = function (NodeType $nodeType) use ($parentNodeType, $nodeName) : void {
            self::requireNodeTypeConstraintsImposedByGrandparentToBeMet($parentNodeType, $nodeName, $nodeType);
        };
        return new self($childNodeType, $requireConstraintsImposedByAncestorsAreMet);
    }

    public function forRegularChildNode(NodeType $nodeType): self
    {
        $parentNodeType = $nodeType;
        $requireConstraintsImposedByAncestorsAreMet = function (NodeType $nodeType) use ($parentNodeType) : void {
            self::requireNodeTypeConstraintsImposedByParentToBeMet($parentNodeType, $nodeType);
        };
        return new self($nodeType, $requireConstraintsImposedByAncestorsAreMet);
    }

    /**
     * @throws NodeConstraintException
     */
    public function requireConstraintsImposedByAncestorsAreMet(NodeType $nodeType): void
    {
        ($this->requireConstraintsImposedByAncestorsAreMet)($nodeType);
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
                    $nodeType->getName(),
                    $parentNodeType->getName()
                ),
                1686417627173
            );
        }
    }

    private static function requireNodeTypeConstraintsImposedByGrandparentToBeMet(NodeType $grandParentNodeType, NodeName $nodeName, NodeType $nodeType): void
    {
        if (!$grandParentNodeType->allowsGrandchildNodeType($nodeName->__toString(), $nodeType)) {
            throw new NodeConstraintException(
                sprintf(
                    'Node type "%s" is not allowed below tethered child nodes "%s" of nodes of type "%s"',
                    $nodeType->getName(),
                    $nodeName->__toString(),
                    $grandParentNodeType->getName()
                ),
                1687541480146
            );
        }
    }
}
