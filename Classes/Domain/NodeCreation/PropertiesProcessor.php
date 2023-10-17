<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Neos\Flow\Property\Exception as PropertyMappingException;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfiguration;

class PropertiesProcessor
{
    private PropertyMapper $propertyMapper;

    public function __construct(PropertyMapper $propertyMapper)
    {
        $this->propertyMapper = $propertyMapper;
    }

    /**
     * We run a few checks and convert the properties.
     * If any of the checks fails we append an exception to the $processingErrors.
     *
     * 1. Check if the NodeType schema has the property declared.
     *
     * 2. It is checked, that the property value is assignable to the property type.
     *   In case the type is class or an array of classes, the property mapper will be used map the given type to it. If it doesn't succeed, we will log an error.
     */
    public function processAndValidateProperties(TransientNode $node, ProcessingErrors $processingErrors): array
    {
        $nodeType = $node->nodeType;
        $validProperties = [];
        foreach ($node->properties as $propertyName => $propertyValue) {
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
                $processingErrors->add(
                    ProcessingError::fromException($exception)->withOrigin(sprintf('Property "%s" in NodeType "%s"', $propertyName, $nodeType->name->value))
                );
            }
        }
        return $validProperties;
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
