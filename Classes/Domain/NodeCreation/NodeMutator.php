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
     * @param \Closure(NodeInterface $currentNode): ?NodeInterface $mutator
     */
    private function __construct(
        \Closure $mutator
    ) {
        $this->mutator = $mutator;
    }

    /**
     * Queues to execute this mutator on the current node
     *
     * @param \Closure(NodeInterface $currentNode): ?NodeInterface $mutator
     */
    public static function unsafeFromClosure(\Closure $mutator): self
    {
        return new self($mutator);
    }

    /**
     * Queues to execute the {@see NodeMutatorCollection} on the current node but the operations wont change the current node.
     */
    public static function isolated(NodeMutatorCollection $nodeMutators): self
    {
        return new self(function (NodeInterface $currentNode) use($nodeMutators) {
            $nodeMutators->apply($currentNode);
        });
    }

    /**
     * Queues to select a child node of the current node
     */
    public static function selectChildNode(NodeName $nodeName): self
    {
        return new self(function (NodeInterface $currentNode) use($nodeName) {
            $nextNode = $currentNode->getNode($nodeName->__toString());
            if (!$nextNode instanceof NodeInterface) {
                throw new \RuntimeException(sprintf('Could not select childNode %s from %s', $nodeName->__toString(), $currentNode));
            }
            return $nextNode;
        });
    }

    /**
     * Queues to create a new node into the current node and select it
     */
    public static function createAndSelectNode(NodeTypeName $nodeTypeName, ?NodeName $nodeName): self
    {
        return new static(function (NodeInterface $currentNode) use($nodeTypeName, $nodeName) {
            $nodeOperations = Bootstrap::$staticObjectManager->get(NodeOperations::class); // hack
            return $nodeOperations->create(
                $currentNode,
                [
                    'nodeType' => $nodeTypeName->getValue(),
                    'nodeName' => $nodeName ? $nodeName->__toString() : null
                ],
                'into'
            );
        });
    }

    /**
     * Queues to set properties on the current node
     */
    public static function setProperties(array $properties): self
    {
        return new static(function (NodeInterface $currentNode) use ($properties) {
            foreach ($properties as $key => $value) {
                $currentNode->setProperty($key, $value);
            }
        });
    }

    /**
     * Applies the operations. The $currentNode being the current node for all operations
     * It will return a new selected/created node or the current node in case only properties were set
     */
    public function apply(NodeInterface $currentNode): NodeInterface
    {
        return ($this->mutator)($currentNode) ?? $currentNode;
    }
}
