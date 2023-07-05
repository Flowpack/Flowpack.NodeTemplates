<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\Exception as PropertyMappingException;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfiguration;

/**
 * @Flow\Proxy(false)
 */
class PropertiesHandler
{
    private ContentSubgraphInterface $subgraph;

    private PropertyMapper $propertyMapper;

    public function __construct(ContentSubgraphInterface $subgraph, PropertyMapper $propertyMapper)
    {
        $this->subgraph = $subgraph;
        $this->propertyMapper = $propertyMapper;
    }

    public function createdFromArrayByTypeDeclaration(array $propertiesAndReferences, NodeType $nodeType): Properties
    {
        $references = [];
        $properties = [];
        foreach ($propertiesAndReferences as $propertyName => $propertyValue) {
            // TODO: remove the next line to initialise the nodeType, once https://github.com/neos/neos-development-collection/issues/4333 is fixed
            $nodeType->getFullConfiguration();
            $declaration = $nodeType->getPropertyType($propertyName);
            if ($declaration === 'reference' || $declaration === 'references') {
                $references[$propertyName] = $propertyValue;
                continue;
            }
            $properties[$propertyName] = $propertyValue;
        }
        return new Properties($properties, $references, $nodeType);
    }

    /**
     * A few checks are run against the properties before they are applied on the node.
     *
     * 1. It is checked, that only properties will be set, that were declared in the NodeType
     *
     * 2. It is checked, that the property value is assignable to the property type.
     *    In case the type is class or an array of classes, the property mapper will be used map the given type to it. If it doesn't succeed, we will log an error.
     */
    public function requireValidProperties(Properties $properties, CaughtExceptions $caughtExceptions): array
    {
        $nodeType = $properties->getNodeType();
        $validProperties = [];
        foreach ($properties->getProperties() as $propertyName => $propertyValue) {
            try {
                $this->assertValidPropertyName($propertyName);
                if (!isset($nodeType->getProperties()[$propertyName])) {
                    throw new PropertyIgnoredException(
                        sprintf(
                            'Because property is not declared in NodeType. Got value `%s`.',
                            json_encode($propertyValue)
                        ),
                        1685869035209
                    );
                }
                $propertyType = PropertyType::fromPropertyOfNodeType($propertyName, $nodeType);

                if (!$propertyType->isMatchedBy($propertyValue)
                    && ($propertyType->isClass() || $propertyType->isArrayOfClass())) {
                    // we try property mapping only for class types or array of classes
                    $propertyMappingConfiguration = new PropertyMappingConfiguration();
                    $propertyMappingConfiguration->allowAllProperties();
                    $propertyValue = $this->propertyMapper->convert($propertyValue, $propertyType->getValue(), $propertyMappingConfiguration);
                    $messages = $this->propertyMapper->getMessages();
                    if ($messages->hasErrors()) {
                        throw new PropertyIgnoredException($messages->getFirstError()->getMessage(), 1686779371122);
                    }
                }

                if (!$propertyType->isMatchedBy($propertyValue)) {
                    throw new PropertyIgnoredException(
                        sprintf(
                            'Because value `%s` is not assignable to property type "%s".',
                            json_encode($propertyValue),
                            $propertyType->getValue()
                        ),
                        1685958105644
                    );
                }
                $validProperties[$propertyName] = $propertyValue;
            } catch (PropertyIgnoredException|PropertyMappingException $exception) {
                $caughtExceptions->add(
                    CaughtException::fromException($exception)->withOrigin(sprintf('Property "%s" in NodeType "%s"', $propertyName, $nodeType->getName()))
                );
            }
        }
        return $validProperties;
    }

    /**
     * @return array<string, NodeAggregateIds>
     */
    public function requireValidReferences(Properties $properties, CaughtExceptions $caughtExceptions): array
    {
        $nodeType = $properties->getNodeType();
        $validReferences = [];
        foreach ($properties->getReferences() as $referenceName => $referenceValue) {
            $referenceType = ReferenceType::fromPropertyOfNodeType($referenceName, $nodeType);

            try {
                if ($referenceType->isReference()) {
                    $nodeAggregateIdentifier = $referenceType->toNodeAggregateId($referenceValue);
                    if ($nodeAggregateIdentifier === null) {
                        continue;
                    }
                    if (!$this->subgraph->findNodeById($nodeAggregateIdentifier)) {
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
                        continue;
                    }
                    foreach ($nodeAggregateIdentifiers as $nodeAggregateIdentifier) {
                        if (!$this->subgraph->findNodeById($nodeAggregateIdentifier)) {
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
                $caughtExceptions->add(
                    CaughtException::fromException($runtimeException)
                        ->withOrigin(sprintf('Reference "%s" in NodeType "%s"', $referenceName, $nodeType->getName()))
                );
                continue;
            }
        }
        return $validReferences;
    }

    /**
     * In the old CR, it was common practice to set internal or meta properties via this syntax: `_hidden` but we don't allow this anymore.
     * @throws PropertyIgnoredException
     */
    private function assertValidPropertyName($propertyName): void
    {
        $legacyInternalProperties = ['_accessRoles', '_contentObject', '_hidden', '_hiddenAfterDateTime', '_hiddenBeforeDateTime', '_hiddenInIndex',
            '_index', '_name', '_nodeType', '_removed', '_workspace'];
        if (!is_string($propertyName) || $propertyName === '') {
            throw new PropertyIgnoredException(sprintf('Because property name must be a non empty string. Got "%s".', $propertyName), 1686149518395);
        }
        if ($propertyName[0] === '_') {
            $lowerPropertyName = strtolower($propertyName);
            foreach ($legacyInternalProperties as $legacyInternalProperty) {
                if ($lowerPropertyName === strtolower($legacyInternalProperty)) {
                    throw new PropertyIgnoredException(sprintf('Because internal legacy property "%s" not implement.', $propertyName), 1686149513158);
                }
            }
        }
    }
}
