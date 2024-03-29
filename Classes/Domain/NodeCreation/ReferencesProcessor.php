<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Neos\ContentRepository\Domain\Model\NodeInterface;

class ReferencesProcessor
{
    public function processAndValidateReferences(TransientNode $node, ProcessingErrors $processingErrors): array
    {
        $nodeType = $node->getNodeType();
        $validReferences = [];
        foreach ($node->getReferences() as $referenceName => $referenceValue) {
            $referenceType = ReferenceType::fromPropertyOfNodeType($referenceName, $nodeType);

            try {
                if ($referenceType->isReference()) {
                    $nodeAggregateIdentifier = $referenceType->toNodeAggregateId($referenceValue);
                    if ($nodeAggregateIdentifier === null) {
                        // not necessary needed, but to reset in case there a default values
                        $validReferences[$referenceName] = null;
                        continue;
                    }
                    if (!($resolvedNode = $node->getSubgraph()->getNodeByIdentifier($nodeAggregateIdentifier->__toString())) instanceof NodeInterface) {
                        throw new InvalidReferenceException(sprintf(
                            'Node with identifier "%s" does not exist.',
                            $nodeAggregateIdentifier->__toString()
                        ), 1687632330292);
                    }
                    $validReferences[$referenceName] = $resolvedNode;
                    continue;
                }

                if ($referenceType->isReferences()) {
                    $nodeAggregateIdentifiers = $referenceType->toNodeAggregateIds($referenceValue);
                    if (count($nodeAggregateIdentifiers) === 0) {
                        // not necessary needed, but to reset in case there a default values
                        $validReferences[$referenceName] = null;
                        continue;
                    }
                    $nodes = [];
                    foreach ($nodeAggregateIdentifiers as $nodeAggregateIdentifier) {
                        if (!($nodes[] = $node->getSubgraph()->getNodeByIdentifier($nodeAggregateIdentifier->__toString())) instanceof NodeInterface) {
                            throw new InvalidReferenceException(sprintf(
                                'Node with identifier "%s" does not exist.',
                                $nodeAggregateIdentifier->__toString()
                            ), 1687632330292);
                        }
                    }
                    $validReferences[$referenceName] = $nodes;
                    continue;
                }
            } catch (InvalidReferenceException $runtimeException) {
                $processingErrors->add(
                    ProcessingError::fromException($runtimeException)
                        ->withOrigin(sprintf('Reference "%s" in NodeType "%s"', $referenceName, $nodeType->getName()))
                );
                continue;
            }
        }
        return $validReferences;
    }
}
