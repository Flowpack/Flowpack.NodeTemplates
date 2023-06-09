<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;

trait JsonSerializeNodeTreeTrait
{
    private function jsonSerializeNodeAndDescendents(Subtree $subtree): array
    {
        $node = $subtree->node;

        return array_filter([
            'nodeTypeName' => $node->nodeTypeName,
            'nodeName' =>  $node->classification->isTethered() ? $node->nodeName : null,
            // todo
            'isDisabled' => false,
            'properties' => $this->serializeValuesInArray(
                iterator_to_array($node->properties->getIterator())
            ),
            // todo
            'references' => [], // $this->serializeValuesInArray($references)
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
