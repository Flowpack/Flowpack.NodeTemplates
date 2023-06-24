<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtException;
use Flowpack\NodeTemplates\Domain\ExceptionHandling\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Template;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationCommands;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class NodeCreationService
{
    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    public function __construct(
        private readonly ContentSubgraphInterface $subgraph,
        private readonly NodeTypeManager $nodeTypeManager
    ) {
    }

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeCreationCommands $commands, CaughtExceptions $caughtExceptions): NodeCreationCommands
    {
        $nodeType = $this->nodeTypeManager->getNodeType($commands->first->nodeTypeName);
        $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($template->getProperties(), $nodeType);

        // set properties

        $initialProperties = $commands->first->initialPropertyValues;

        $initialProperties = $initialProperties->merge(
            PropertyValuesToWrite::fromArray($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions))
        );

        $initialProperties = $this->ensureNodeHasUriPathSegment(
            $nodeType,
            $commands->first->nodeName,
            $commands->first->originDimensionSpacePoint->toDimensionSpacePoint(),
            $initialProperties,
            $template
        );

        return $this->applyTemplateRecursively(
            $template->getChildNodes(),
            new ToBeCreatedNode(
                $commands->first->contentStreamId,
                $commands->first->originDimensionSpacePoint,
                $commands->first->nodeAggregateId,
                $nodeType,
            ),
            $commands->withInitialPropertyValues($initialProperties)->withAdditionalCommands(
                ...$this->createReferencesCommands(
                    $commands->first->contentStreamId,
                    $commands->first->nodeAggregateId,
                    $commands->first->originDimensionSpacePoint,
                    $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
                )
            ),
            $caughtExceptions
        );
    }

    private function applyTemplateRecursively(Templates $templates, ToBeCreatedNode $parentNode, NodeCreationCommands $commands, CaughtExceptions $caughtExceptions): NodeCreationCommands
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->nodeType->hasAutoCreatedChildNode($template->getName())) {
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->value), 1685999829307))
                    );
                    // we continue processing the node
                }

                $nodeType = $parentNode->nodeType->getTypeOfAutoCreatedChildNode($template->getName());
                $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($template->getProperties(), $nodeType);

                $commands = $commands->withAdditionalCommands(
                    new SetNodeProperties(
                        $parentNode->contentStreamId,
                        $nodeAggregateId = NodeAggregateId::fromParentNodeAggregateIdAndNodeName(
                            $parentNode->nodeAggregateId,
                            $template->getName()
                        ),
                        $parentNode->originDimensionSpacePoint,
                        PropertyValuesToWrite::fromArray($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions))
                    ),
                    ...$this->createReferencesCommands(
                        $parentNode->contentStreamId,
                        $nodeAggregateId,
                        $parentNode->originDimensionSpacePoint,
                        $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
                    )
                );

                $commands = $this->applyTemplateRecursively(
                    $template->getChildNodes(),
                    $parentNode->withNodeTypeAndNodeAggregateId(
                        $nodeType,
                        $nodeAggregateId
                    ),
                    $commands,
                    $caughtExceptions
                );
                continue;
            }

            if ($template->getType() === null) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be set for non auto created child nodes.'), 1685999829307))
                );
                continue;
            }
            if (!$this->nodeTypeManager->hasNodeType($template->getType())) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->value), 1685999795564))
                );
                continue;
            }

            $nodeType = $this->nodeTypeManager->getNodeType($template->getType());

            if ($nodeType->isAbstract()) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Template requires type to be a non abstract NodeType. Got: "%s".', $template->getType()->value), 1686417628976))
                );
                continue;
            }

            if (!$parentNode->nodeType->allowsChildNodeType($nodeType)) {
                $caughtExceptions->add(
                    CaughtException::fromException(new \RuntimeException(sprintf('Node type "%s" is not allowed for child nodes of type %s', $template->getType()->value, $parentNode->nodeType->name->value), 1686417627173))
                );
                continue;
            }

            // todo maybe check also allowsGrandchildNodeType

            $propertiesAndReferences = PropertiesAndReferences::createFromArrayAndTypeDeclarations($template->getProperties(), $nodeType);

            $nodeName = $template->getName() ?? NodeName::fromString(uniqid('node-', false));

            $initialProperties = PropertyValuesToWrite::fromArray($propertiesAndReferences->requireValidProperties($nodeType, $caughtExceptions));

            $initialProperties = $this->ensureNodeHasUriPathSegment(
                $nodeType,
                $nodeName,
                $parentNode->originDimensionSpacePoint->toDimensionSpacePoint(),
                $initialProperties,
                $template
            );

            $commands = $commands->withAdditionalCommands(
                new CreateNodeAggregateWithNode(
                    $parentNode->contentStreamId,
                    $nodeAggregateId = NodeAggregateId::create(),
                    $template->getType(),
                    $parentNode->originDimensionSpacePoint,
                    $parentNode->nodeAggregateId,
                    nodeName: $nodeName,
                    initialPropertyValues: $initialProperties
                ),
                ...$this->createReferencesCommands(
                    $parentNode->contentStreamId,
                    $nodeAggregateId,
                    $parentNode->originDimensionSpacePoint,
                    $propertiesAndReferences->requireValidReferences($nodeType, $this->subgraph, $caughtExceptions)
                )
            );


            $commands = $this->applyTemplateRecursively(
                $template->getChildNodes(),
                $parentNode->withNodeTypeAndNodeAggregateId(
                    $nodeType,
                    $nodeAggregateId
                ),
                $commands,
                $caughtExceptions
            );
        }

        return $commands;
    }

    /**
     * @param array<string, NodeAggregateIds> $references
     * @return list<SetNodeReferences>
     */
    private function createReferencesCommands(ContentStreamId $contentStreamId, NodeAggregateId $nodeAggregateId, OriginDimensionSpacePoint $originDimensionSpacePoint, array $references): array
    {
        $commands = [];
        foreach ($references as $name => $nodeAggregateIds) {
            $commands[] = new SetNodeReferences(
                $contentStreamId,
                $nodeAggregateId,
                $originDimensionSpacePoint,
                ReferenceName::fromString($name),
                NodeReferencesToWrite::fromNodeAggregateIds($nodeAggregateIds)
            );
        }
        return $commands;
    }

    /**
     * All document node types get a uri path segmfent; if it is not explicitly set in the properties,
     * it should be built based on the title property
     */
    private function ensureNodeHasUriPathSegment(
        NodeType $nodeType,
        ?NodeName $nodeName,
        DimensionSpacePoint $dimensionSpacePoint,
        PropertyValuesToWrite $propertiesToWrite,
        Template|RootTemplate $template
    ): PropertyValuesToWrite {
        if (!$nodeType->isOfType('Neos.Neos:Document')) {
            return $propertiesToWrite;
        }
        $properties = $template->getProperties();
        if (isset($properties['uriPathSegment'])) {
            return $propertiesToWrite;
        }

        return $propertiesToWrite->withValue(
            'uriPathSegment',
            $this->nodeUriPathSegmentGenerator->generateUriPathSegmentFromTextForDimension(
                $properties['title'] ?? $nodeName?->value ?? uniqid('', true),
                $dimensionSpacePoint
            )
        );
    }
}
