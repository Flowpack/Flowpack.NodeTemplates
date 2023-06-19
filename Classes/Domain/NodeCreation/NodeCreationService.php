<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Template;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeConstraintException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Neos\Service\NodeOperations;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;
use Neos\Flow\Property\Exception as PropertyWasNotMappedException;

class NodeCreationService
{
    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * @var NodeTypeManager
     * @Flow\Inject
     */
    protected $nodeTypeManager;

    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * @Flow\Inject
     * @var PropertyMapper
     */
    protected $propertyMapper;

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeInterface $node, CaughtExceptions $caughtExceptions): void
    {
        $nodeType = $node->getNodeType();
        $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($this->convertProperties($nodeType, $template->getProperties(), $caughtExceptions), $nodeType);

        // set properties
        foreach ($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions) as $key => $value) {
            $node->setProperty($key, $value);
        }

        // set references
        foreach ($propertiesAndReferences->requireValidReferences($nodeType, $node->getContext(), $caughtExceptions) as $key => $value) {
            $node->setProperty($key, $value);
        }

        $this->ensureNodeHasUriPathSegment($node, $template);
        $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
    }

    private function applyTemplateRecursively(Templates $templates, NodeInterface $parentNode, CaughtExceptions $caughtExceptions): void
    {
        // `hasAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNode->getNodeType()->getAutoCreatedChildNodes();
        foreach ($templates as $template) {
            if ($template->getName() && isset($parentNodesAutoCreatedChildNodes[$template->getName()->__toString()])) {
                $node = $parentNode->getNode($template->getName()->__toString());
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->getValue()), 1685999829307))
                    );
                    // we continue processing the node
                }
            } else {
                if ($template->getType() === null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be set for non auto created child nodes.'), 1685999829307))
                    );
                    continue;
                }
                if (!$this->nodeTypeManager->hasNodeType($template->getType()->getValue())) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->getValue()), 1685999795564))
                    );
                    continue;
                }

                $nodeType = $this->nodeTypeManager->getNodeType($template->getType()->getValue());

                if ($nodeType->isAbstract()) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a non abstract NodeType. Got: "%s".', $template->getType()->getValue()), 1686417628976))
                    );
                    continue;
                }

                if (!$parentNode->getNodeType()->allowsChildNodeType($nodeType)) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Node type "%s" is not allowed for child nodes of type %s', $template->getType()->getValue(), $parentNode->getNodeType()->getName()), 1686417627173))
                    );
                    continue;
                }

                // todo maybe check also explicitly for allowsGrandchildNodeType (we do this currently like below)
                try {
                    $node = $this->nodeOperations->create(
                        $parentNode,
                        [
                            'nodeType' => $template->getType()->getValue(),
                            'nodeName' => $template->getName() ? $template->getName()->__toString() : null
                        ],
                        'into'
                    );
                } catch (NodeConstraintException $nodeConstraintException) {
                    $caughtExceptions->add(
                        CaughtException::fromException($nodeConstraintException)
                    );
                    continue; // try the next childNode
                }
            }
            $nodeType = $node->getNodeType();
            $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($this->convertProperties($nodeType, $template->getProperties(), $caughtExceptions), $nodeType);

            // set properties
            foreach ($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions) as $key => $value) {
                $node->setProperty($key, $value);
            }

            // set references
            foreach ($propertiesAndReferences->requireValidReferences($nodeType, $node->getContext(), $caughtExceptions) as $key => $value) {
                $node->setProperty($key, $value);
            }

            $this->ensureNodeHasUriPathSegment($node, $template);
            $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
        }
    }

    /**
     * All document node types get a uri path segment; if it is not explicitly set in the properties,
     * it should be built based on the title property
     *
     * @param Template|RootTemplate $template
     */
    private function ensureNodeHasUriPathSegment(NodeInterface $node, $template)
    {
        if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
            return;
        }
        $properties = $template->getProperties();
        if (isset($properties['uriPathSegment'])) {
            return;
        }
        $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, $properties['title'] ?? null));
    }

    private function convertProperties(NodeType $nodeType, array $properties, CaughtExceptions $caughtExceptions): array
    {
        // TODO combine with PropertiesAndReferences::requireValidProperties
        foreach ($nodeType->getConfiguration('properties') as $propertyName => $propertyConfiguration) {
            if (!isset($properties[$propertyName])) {
                continue;
            }
            $propertyType = $nodeType->getPropertyType($propertyName);
            if ($propertyType === 'references' || $propertyType === 'reference') {
                continue;
            }
            $propertyType = PropertyType::fromPropertyOfNodeType($propertyName, $nodeType);
            $propertyValue = $properties[$propertyName];
            if (!$propertyType->isClass()
                && !($propertyType->isArrayOf() && $propertyType->getArrayOfType()->isClass())) {
                // property mapping only for class types or array of classes!
                continue;
            }
            try {
                $propertyMappingConfiguration = new PropertyMappingConfiguration();
                $propertyMappingConfiguration->allowAllProperties();

                $properties[$propertyName] = $this->propertyMapper->convert($propertyValue, $propertyType->getValue(), $propertyMappingConfiguration);
                $messages = $this->propertyMapper->getMessages();
                if ($messages->hasErrors()) {
                    throw new PropertyWasNotMappedException($this->propertyMapper->getMessages()->getFirstError()->getMessage(), 1686779371122);
                }
            } catch (PropertyWasNotMappedException $exception) {
                $caughtExceptions->add(CaughtException::fromException(
                    $exception
                )->withOrigin(sprintf('Property "%s" in NodeType "%s"', $propertyName, $nodeType->getName())));
                unset($properties[$propertyName]);
            }
        }
        return $properties;
    }
}
