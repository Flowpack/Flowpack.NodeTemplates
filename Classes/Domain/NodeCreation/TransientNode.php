<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class TransientNode
{
    private NodeType $nodeType;

    private ?NodeName $tetheredNodeName;

    private ?NodeType $tetheredParentNodeType;

    private function __construct(NodeType $nodeType, ?NodeName $tetheredNodeName, ?NodeType $tetheredParentNodeType)
    {
        $this->nodeType = $nodeType;
        $this->tetheredNodeName = $tetheredNodeName;
        $this->tetheredParentNodeType = $tetheredParentNodeType;
        if ($tetheredNodeName !== null) {
            assert($tetheredParentNodeType !== null);
        }
    }

    public static function forRegular(NodeType $nodeType): self
    {
        return new self($nodeType, null, null);
    }

    public function forTetheredChildNode(NodeName $nodeName): self
    {
        // `getTypeOfAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $this->nodeType->getAutoCreatedChildNodes();
        $childNodeType = $parentNodesAutoCreatedChildNodes[$nodeName->__toString()] ?? null;
        if (!$childNodeType instanceof NodeType) {
            throw new \InvalidArgumentException('forTetheredChildNode only works for tethered nodes.');
        }
        return new self($childNodeType, $nodeName, $this->nodeType);
    }

    public function forRegularChildNode(NodeType $nodeType): self
    {
        return new self($nodeType, null, null);
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
