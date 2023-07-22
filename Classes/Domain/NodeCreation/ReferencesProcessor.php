<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;

class ReferencesProcessor
{
    /**
     * @return array<string, NodeAggregateIds>
     */
    public function processAndValidateReferences(TransientNode $node, ProcessingErrors $processingErrors): array
    {
        $nodeType = $node->nodeType;
        $validReferences = [];
        foreach ($node->references as $referenceName => $referenceValue) {
            $referenceType = ReferenceType::fromPropertyOfNodeType($referenceName, $nodeType);

            try {
                if ($referenceType->isReference()) {
                    $nodeAggregateIdentifier = $referenceType->toNodeAggregateId($referenceValue);
                    if ($nodeAggregateIdentifier === null) {
                        // not necessary needed, but to reset in case there a default values
                        $validReferences[$referenceName] = NodeAggregateIds::createEmpty();
                        continue;
                    }
                    if (!$node->subgraph->findNodeById($nodeAggregateIdentifier)) {
                        throw new InvalidReferenceException(sprintf(
                            'Node with identifier "%s" does not exist.',
                            $nodeAggregateIdentifier->value
                        ), 1687632330292);
                    }
                    $validReferences[$referenceName] = NodeAggregateIds::create($nodeAggregateIdentifier);
                    continue;
                }

                if ($referenceType->isReferences()) {
                    $nodeAggregateIdentifiers = $referenceType->toNodeAggregateIds($referenceValue);
                    if (count(iterator_to_array($nodeAggregateIdentifiers)) === 0) {
                        // not necessary needed, but to reset in case there a default values
                        $validReferences[$referenceName] = NodeAggregateIds::createEmpty();
                        continue;
                    }
                    foreach ($nodeAggregateIdentifiers as $nodeAggregateIdentifier) {
                        if (!$node->subgraph->findNodeById($nodeAggregateIdentifier)) {
                            throw new InvalidReferenceException(sprintf(
                                'Node with identifier "%s" does not exist.',
                                $nodeAggregateIdentifier->value
                            ), 1687632330292);
                        }
                    }
                    $validReferences[$referenceName] = $nodeAggregateIdentifiers;
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
