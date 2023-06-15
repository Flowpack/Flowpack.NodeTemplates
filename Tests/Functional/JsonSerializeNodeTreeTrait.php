<?php

namespace Flowpack\NodeTemplates\Tests\Functional;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Utility\ObjectAccess;

trait JsonSerializeNodeTreeTrait
{
    private function jsonSerializeNodeAndDescendents(NodeInterface $node): array
    {
        $nodeType = $node->getNodeType();
        $references = [];
        $properties = [];
        foreach ($node->getProperties() as $propertyName => $propertyValue) {
            $declaration = $nodeType->getPropertyType($propertyName);
            if ($declaration === 'reference' || $declaration === 'references') {
                $references[$propertyName] = [];
                foreach ($declaration === 'reference' ? [$propertyValue] : $propertyValue as $reference) {
                    $references[$propertyName][] = array_filter([
                        'node' => $reference,
                        'properties' => []
                    ]);
                }
                continue;
            }
            $properties[$propertyName] = $propertyValue;
        }
        return array_filter([
            'nodeTypeName' => $node->getNodeType()->getName(),
            'nodeName' => $node->isAutoCreated() ? $node->getName() : null,
            'isDisabled' => $node->isHidden(),
            'properties' => $this->serializeValuesInArray($properties),
            'references' => $this->serializeValuesInArray($references),
            'childNodes' => array_map(
                fn ($node) => $this->jsonSerializeNodeAndDescendents($node),
                $node->getChildNodes('Neos.Neos:Node')
            )
        ]);
    }

    private function serializeValuesInArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = $this->serializeValuesInArray($value);
            } elseif ($value instanceof NodeInterface) {
                $value = sprintf('Node(%s, %s)', $value->getIdentifier(), $value->getNodeType()->getName());
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
