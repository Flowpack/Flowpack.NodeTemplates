<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\Neos\Domain\SubtreeTagging\NeosSubtreeTag;
use Neos\Utility\ObjectAccess;

trait JsonSerializeNodeTreeTrait
{
    private readonly ContentRepository $contentRepository;

    /**
     * @return array<string,mixed>
     */
    private function jsonSerializeNodeAndDescendents(Subtree $subtree): array
    {
        $node = $subtree->node;

        $subgraph = $this->contentRepository->getContentGraph($node->workspaceName)->getSubgraph(
            $node->dimensionSpacePoint,
            $node->visibilityConstraints
        );

        $references = $subgraph->findReferences($node->aggregateId, FindReferencesFilter::create());

        $referencesArray = [];
        foreach ($references as $reference) {
            $referencesArray[$reference->name->value] ??= [];
            $referencesArray[$reference->name->value][] = array_filter([
                'node' => sprintf('Node(%s, %s)', $reference->node->aggregateId->value, $reference->node->nodeTypeName->value),
                'properties' => iterator_to_array($reference->properties ?? [])
            ]);
        }

        return array_filter([
            'nodeTypeName' => $node->nodeTypeName,
            'nodeName' =>  $node->classification->isTethered() ? $node->name : null,
            'isDisabled' => $node->tags->contain(NeosSubtreeTag::disabled()),
            'properties' => $this->serializeValuesInArray(
                iterator_to_array($node->properties->getIterator())
            ),
            'references' => $referencesArray,
            'childNodes' => array_map(
                fn ($subtree) => $this->jsonSerializeNodeAndDescendents($subtree),
                iterator_to_array($subtree->children)
            )
        ]);
    }

    /**
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function serializeValuesInArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->serializeValuesInArray($value);
            } elseif ($value instanceof Node) {
                $value = sprintf('Node(%s, %s)', $value->aggregateId->value, $value->nodeTypeName->value);
            } elseif ($value instanceof \JsonSerializable) {
                $value = $value->jsonSerialize();
                if (is_array($value)) {
                    $value = $this->serializeValuesInArray($value);
                }
            } elseif (is_object($value)) {
                $id = ObjectAccess::getProperty($value, 'Persistence_Object_Identifier', true);
                $value = sprintf('object(%s%s)', get_class($value), $id ? (sprintf(', %s', $id)) : '');
            } else {
                continue;
            }
            $array[$key] = $value;
        }
        return $array;
    }
}
