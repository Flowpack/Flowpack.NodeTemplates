<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class PropertiesAndReferences
{
    private array $properties;

    private array $references;

    private function __construct(array $properties, array $references)
    {
        $this->properties = $properties;
        $this->references = $references;
    }

    public static function createFromArrayAndTypeDeclarations(array $propertiesAndReferences, NodeType $nodeType): self
    {
        $references = [];
        $properties = [];
        foreach ($propertiesAndReferences as $propertyName => $propertyValue) {
            // TODO remove next line to initialize the nodeTpye once https://github.com/neos/neos-development-collection/issues/4333 is fixed
            $nodeType->getFullConfiguration();
            $declaration = $nodeType->getPropertyType($propertyName);
            if ($declaration === 'reference' || $declaration === 'references') {
                $references[$propertyName] = $propertyValue;
                continue;
            }
            $properties[$propertyName] = $propertyValue;
        }
        return new self($properties, $references);
    }

    /**
     * A few checks are run against the properties before they are applied on the node.
     *
     * 1. It is checked, that only properties will be set, that were declared in the NodeType
     *
     * 2. In case the property is a select-box, it is checked, that the current value is a valid option of the select-box
     *
     * 3. It is made sure is that a property value is not null when there is a default value:
     *  In case that due to a condition in the nodeTemplate `null` is assigned to a node property, it will override the defaultValue.
     *  This is a problem, as setting `null` might not be possible via the Neos UI and the Fusion rendering is most likely not going to handle this edge case.
     *  Related discussion {@link https://github.com/Flowpack/Flowpack.NodeTemplates/issues/41}
     */
    public function requireValidProperties(NodeType $nodeType, CaughtExceptions $caughtExceptions): array
    {
        $validProperties = [];
        foreach ($this->properties as $propertyName => $propertyValue) {
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
            } catch (PropertyIgnoredException $propertyNotSetException) {
                $caughtExceptions->add(
                    CaughtException::fromException($propertyNotSetException)->withOrigin(sprintf('Property "%s" in NodeType "%s"', $propertyName, $nodeType->getName()))
                );
            }
        }
        return $validProperties;
    }

    public function requireValidReferences(NodeType $nodeType, Context $subgraph, CaughtExceptions $caughtExceptions): array
    {
        $validReferences = [];
        foreach ($this->references as $referenceName => $referenceValue) {
            $referenceType = ReferenceType::fromPropertyOfNodeType($referenceName, $nodeType);
            if (!$referenceType->isMatchedBy($referenceValue, $subgraph)) {
                $caughtExceptions->add(CaughtException::fromException(new \RuntimeException(
                    sprintf(
                        'Reference could not be set, because node reference(s) %s cannot be resolved.',
                        json_encode($referenceValue)
                    ),
                    1685958176560
                ))->withOrigin(sprintf('Reference "%s" in NodeType "%s"', $referenceName, $nodeType->getName())));
                continue;
            }
            $validReferences[$referenceName] = $referenceValue;
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
