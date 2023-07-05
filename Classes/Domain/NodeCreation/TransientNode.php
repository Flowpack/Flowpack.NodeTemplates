<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class TransientNode
{
    private NodeType $nodeType;

    private ?NodeName $tetheredNodeName;

    private ?NodeType $tetheredParentNodeType;

    private NodeTypeManager $nodeTypeManager;

    private Context $subgraph;

    private array $properties;

    private array $references;

    private function __construct(NodeType $nodeType, ?NodeName $tetheredNodeName, ?NodeType $tetheredParentNodeType, NodeTypeManager $nodeTypeManager, Context $subgraph, array $rawProperties)
    {
        $this->nodeType = $nodeType;
        $this->tetheredNodeName = $tetheredNodeName;
        $this->tetheredParentNodeType = $tetheredParentNodeType;
        if ($tetheredNodeName !== null) {
            assert($tetheredParentNodeType !== null);
        }
        $this->nodeTypeManager = $nodeTypeManager;
        $this->subgraph = $subgraph;

        // split properties and references by type declaration
        $properties = [];
        $references = [];
        foreach ($rawProperties as $propertyName => $propertyValue) {
            // TODO: remove the next line to initialise the nodeType, once https://github.com/neos/neos-development-collection/issues/4333 is fixed
            $this->nodeType->getFullConfiguration();
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

    public static function forRegular(NodeType $nodeType, NodeTypeManager $nodeTypeManager,  Context $subgraph, array $rawProperties): self
    {
        return new self($nodeType, null, null, $nodeTypeManager, $subgraph, $rawProperties);
    }

    public function forTetheredChildNode(NodeName $nodeName, array $rawProperties): self
    {
        // `getTypeOfAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $this->nodeType->getAutoCreatedChildNodes();
        $childNodeType = $parentNodesAutoCreatedChildNodes[$nodeName->__toString()] ?? null;
        if (!$childNodeType instanceof NodeType) {
            throw new \InvalidArgumentException('forTetheredChildNode only works for tethered nodes.');
        }
        return new self($childNodeType, $nodeName, $this->nodeType, $this->nodeTypeManager, $this->subgraph, $rawProperties);
    }

    public function forRegularChildNode(NodeType $nodeType, array $rawProperties): self
    {
        return new self($nodeType, null, null, $this->nodeTypeManager, $this->subgraph, $rawProperties);
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

    public function getNodeTypeManager(): NodeTypeManager
    {
        return $this->nodeTypeManager;
    }

    public function getSubgraph(): Context
    {
        return $this->subgraph;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getReferences(): array
    {
        return $this->references;
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
