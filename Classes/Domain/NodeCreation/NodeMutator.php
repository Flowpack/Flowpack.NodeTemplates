<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Bootstrap;
use Neos\Neos\Service\NodeOperations;

/**
 * We queue changes on nodes instead of making them directly.
 * This allows us to check if the template is valid by cr perspective, without actually applying it,
 * as all checks are executed before the Mutators are run.
 *
 * A mutator should only queue a mutation, that is guaranteed to succeed, as there is no exception handing.
 *
 * @Flow\Proxy(false)
 */
class NodeMutator
{
    private \Closure $mutator;

    /**
     * @param \Closure(NodeInterface $nodePointer): ?NodeInterface $mutator
     */
    private function __construct(
        \Closure $mutator
    ) {
        $this->mutator = $mutator;
    }

    /**
     * Queues to set properties on the current node.
     *
     * Preserves the current node pointer.
     */
    public static function setProperties(array $properties): self
    {
        return new static(function (NodeInterface $nodePointer) use ($properties) {
            foreach ($properties as $key => $value) {
                $nodePointer->setProperty($key, $value);
            }
        });
    }

    /**
     * Queues to execute the collection {@see NodeMutatorCollection} on the current node.
     * Any selections made in the collection {@see self::selectChildNode()} won't change the pointer to $this current node.
     *
     * Preserves the current node pointer.
     */
    public static function isolated(NodeMutatorCollection $nodeMutators): self
    {
        return new self(function (NodeInterface $nodePointer) use($nodeMutators) {
            $nodeMutators->executeWithStartingNode($nodePointer);
        });
    }

    /**
     * Queues to select a child node of the current node.
     *
     * Modifies the node pointer.
     */
    public static function selectChildNode(NodeName $nodeName): self
    {
        return new self(function (NodeInterface $nodePointer) use($nodeName) {
            $nextNode = $nodePointer->getNode($nodeName->__toString());
            if (!$nextNode instanceof NodeInterface) {
                throw new \RuntimeException(sprintf('Could not select childNode %s from %s', $nodeName->__toString(), $nodePointer));
            }
            return $nextNode;
        });
    }

    /**
     * Queues to create a new node into the current node and select it.
     *
     * Modifies the node pointer.
     */
    public static function createAndSelectNode(NodeTypeName $nodeTypeName, ?NodeName $nodeName): self
    {
        return new static(function (NodeInterface $nodePointer) use($nodeTypeName, $nodeName) {
            $nodeOperations = Bootstrap::$staticObjectManager->get(NodeOperations::class); // hack
            return $nodeOperations->create(
                $nodePointer,
                [
                    'nodeType' => $nodeTypeName->getValue(),
                    'nodeName' => $nodeName ? $nodeName->__toString() : null
                ],
                'into'
            );
        });
    }

    /**
     * Queues to execute this mutator on the current node
     *
     * Should preserve the current node pointer!
     *
     * @param \Closure(NodeInterface $nodePointer): void $mutator
     */
    public static function unsafeFromClosure(\Closure $mutator): self
    {
        return new self($mutator);
    }

    /**
     * Applies this operation
     * For multiple operations: {@see NodeMutatorCollection::executeWithStartingNode()}
     *
     * @param NodeInterface $nodePointer being the current node for this operation
     * @return NodeInterface a new selected/created $nodePointer or the current node in case for example only properties were set
     */
    public function executeWithNodePointer(NodeInterface $nodePointer): NodeInterface
    {
        return ($this->mutator)($nodePointer) ?? $nodePointer;
    }
}
