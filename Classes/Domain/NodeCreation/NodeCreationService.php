<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Template;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\Exception as PropertyWasNotMappedException;
use Neos\Flow\Property\PropertyMapper;
use Neos\Flow\Property\PropertyMappingConfiguration;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class NodeCreationService
{
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

    private Context $subgraph;

    public function __construct(Context $subgraph)
    {
        $this->subgraph = $subgraph;
    }

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function createMutatorCollection(RootTemplate $template, ToBeCreatedNode $node, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $nodeType = $node->getNodeType();

        $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($this->convertProperties($nodeType, $template->getProperties(), $caughtExceptions), $nodeType);

        $properties = array_merge(
            $propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions),
            $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
        );

        $nodeMutators = NodeMutatorCollection::from(
            NodeMutator::setProperties($properties),
            $this->createMutatorForUriPathSegment($template),
        )->merge(
            $this->createMutatorCollectionFromTemplate(
                $template->getChildNodes(),
                new ToBeCreatedNode($nodeType),
                $caughtExceptions
            )
        );

        return $nodeMutators;
    }

    private function createMutatorCollectionFromTemplate(Templates $templates, ToBeCreatedNode $parentNode, CaughtExceptions $caughtExceptions): NodeMutatorCollection
    {
        $nodeMutators = NodeMutatorCollection::empty();

        // `hasAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNode->getNodeType()->getAutoCreatedChildNodes();
        foreach ($templates as $template) {
            if ($template->getName() && isset($parentNodesAutoCreatedChildNodes[$template->getName()->__toString()])) {
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->getValue()), 1685999829307))
                    );
                    // we continue processing the node
                }

                $nodeType = $parentNodesAutoCreatedChildNodes[$template->getName()->__toString()];
                $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($this->convertProperties($nodeType, $template->getProperties(), $caughtExceptions), $nodeType);

                $properties = array_merge(
                    $propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions),
                    $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
                );

                $nodeMutators = $nodeMutators->withNodeMutators(
                    NodeMutator::isolated(
                        NodeMutatorCollection::from(
                            NodeMutator::selectChildNode($template->getName()),
                            NodeMutator::setProperties($properties)
                        )->merge($this->createMutatorCollectionFromTemplate(
                            $template->getChildNodes(),
                            new ToBeCreatedNode($nodeType),
                            $caughtExceptions
                        ))
                    )
                );

                continue;

            }
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

            $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($this->convertProperties($nodeType, $template->getProperties(), $caughtExceptions), $nodeType);

            $properties = array_merge(
                $propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions),
                $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
            );

            $nodeMutators = $nodeMutators->withNodeMutators(
                NodeMutator::isolated(
                    NodeMutatorCollection::from(
                        NodeMutator::createAndSelectNode($template->getType(), $template->getName()),
                        NodeMutator::setProperties($properties),
                        $this->createMutatorForUriPathSegment($template)
                    )->merge($this->createMutatorCollectionFromTemplate(
                        $template->getChildNodes(),
                        new ToBeCreatedNode($nodeType),
                        $caughtExceptions
                    ))
                )
            );

        }

        return $nodeMutators;
    }

    /**
     * All document node types get a uri path segment; if it is not explicitly set in the properties,
     * it should be built based on the title property
     *
     * @param Template|RootTemplate $template
     */
    private function createMutatorForUriPathSegment($template): NodeMutator
    {
        $properties = $template->getProperties();
        return NodeMutator::unsafeFromClosure(function (NodeInterface $previousNode) use ($properties) {
            if (!$previousNode->getNodeType()->isOfType('Neos.Neos:Document')) {
                return;
            }
            if (isset($properties['uriPathSegment'])) {
                return;
            }
            $previousNode->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($previousNode, $properties['title'] ?? null));
        });
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
            if (!$propertyType->isClass() && !$propertyType->isArrayOfClass()) {
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
