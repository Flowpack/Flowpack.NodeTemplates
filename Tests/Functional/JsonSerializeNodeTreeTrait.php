<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\ContentRepository\Core\Projection\NodeHiddenState\NodeHiddenStateFinder;

trait JsonSerializeNodeTreeTrait
{
    private readonly ContentRepository $contentRepository;

    private function jsonSerializeNodeAndDescendents(Subtree $subtree): array
    {
        $hiddenStateFinder = $this->contentRepository->projectionState(NodeHiddenStateFinder::class);

        $node = $subtree->node;

        $subgraph = $this->contentRepository->getContentGraph()->getSubgraph(
            $node->subgraphIdentity->contentStreamId,
            $node->subgraphIdentity->dimensionSpacePoint,
            $node->subgraphIdentity->visibilityConstraints
        );

        $references = $subgraph->findReferences($node->nodeAggregateId, FindReferencesFilter::create());

        $referencesArray = [];
        foreach ($references as $reference) {
            $referencesArray[$reference->name->value] ??= [];
            $referencesArray[$reference->name->value][] = array_filter([
                'node' => sprintf('Node(%s, %s)', $reference->node->nodeAggregateId->value, $reference->node->nodeTypeName->value),
                'properties' => iterator_to_array($reference->properties ?? [])
            ]);
        }

        return array_filter([
            'nodeTypeName' => $node->nodeTypeName,
            'nodeName' =>  $node->classification->isTethered() ? $node->nodeName : null,
            'isDisabled' => $hiddenStateFinder->findHiddenState(
                $node->subgraphIdentity->contentStreamId,
                $node->originDimensionSpacePoint->toDimensionSpacePoint(),
                $node->nodeAggregateId
            )->isHidden,
            'properties' => $this->serializeValuesInArray(
                iterator_to_array($node->properties->getIterator())
            ),
            'references' => $referencesArray,
            'childNodes' => array_map(
                fn ($subtree) => $this->jsonSerializeNodeAndDescendents($subtree),
                $subtree->children
            )
        ]);
    }

    private function serializeValuesInArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->serializeValuesInArray($value);
            } elseif ($value instanceof Node) {
                $value = sprintf('Node(%s, %s)', $value->nodeAggregateId->value, $value->nodeTypeName->value);
            } elseif ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
                if (is_array($value)) {
                    $value = $this->serializeValuesInArray($value);
                }
            } elseif (is_object($value)) {
                $value = sprintf('object(%s)', get_class($value));
            } else {
                continue;
            }
            $array[$key] = $value;
        }
        return $array;
    }
}
