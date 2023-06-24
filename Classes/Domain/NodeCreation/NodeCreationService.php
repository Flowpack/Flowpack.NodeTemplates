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
use Neos\Flow\Property\PropertyMapper;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationCommands;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class NodeCreationService
{
    private readonly NodeTypeManager $nodeTypeManager;

    private readonly NodeUriPathSegmentGenerator $nodeUriPathSegmentGenerator;

    private PropertiesHandler $propertiesHandler;

    public function __construct(
        ContentSubgraphInterface $subgraph,
        NodeTypeManager $nodeTypeManager,
        PropertyMapper $propertyMapper,
        NodeUriPathSegmentGenerator $nodeUriPathSegmentGenerator
    ) {
        $this->nodeTypeManager = $nodeTypeManager;
        $this->nodeUriPathSegmentGenerator = $nodeUriPathSegmentGenerator;
        $this->propertiesHandler = new PropertiesHandler($subgraph, $propertyMapper);
    }

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeCreationCommands $commands, CaughtExceptions $caughtExceptions): NodeCreationCommands
    {
        $nodeType = $this->nodeTypeManager->getNodeType($commands->first->nodeTypeName);
        $properties = $this->propertiesHandler->createdFromArrayByTypeDeclaration($template->getProperties(), $nodeType);

        $initialProperties = $commands->first->initialPropertyValues;

        $initialProperties = $initialProperties->merge(
            PropertyValuesToWrite::fromArray(
                $this->propertiesHandler->requireValidProperties($properties, $caughtExceptions)
            )
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
            ToBeCreatedNode::fromRegular(
                $commands->first->contentStreamId,
                $commands->first->originDimensionSpacePoint,
                $commands->first->nodeAggregateId,
                $nodeType
            ),
            $commands->withInitialPropertyValues($initialProperties)->withAdditionalCommands(
                ...$this->createReferencesCommands(
                    $commands->first->contentStreamId,
                    $commands->first->nodeAggregateId,
                    $commands->first->originDimensionSpacePoint,
                    $this->propertiesHandler->requireValidReferences($properties, $caughtExceptions)
                )
            ),
            $caughtExceptions
        );
    }

    private function applyTemplateRecursively(Templates $templates, ToBeCreatedNode $parentNode, NodeCreationCommands $commands, CaughtExceptions $caughtExceptions): NodeCreationCommands
    {
        // `hasAutoCreatedChildNode` actually has a bug; it looks up the NodeName parameter against the raw configuration instead of the transliterated NodeName
        // https://github.com/neos/neos-ui/issues/3527
        $parentNodesAutoCreatedChildNodes = $parentNode->getNodeType()->getAutoCreatedChildNodes();
        foreach ($templates as $template) {
            if ($template->getName() && isset($parentNodesAutoCreatedChildNodes[$template->getName()->value])) {
                if ($template->getType() !== null) {
                    $caughtExceptions->add(
                        CaughtException::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->value), 1685999829307))
                    );
                    // we continue processing the node
                }

                $nodeType = $parentNodesAutoCreatedChildNodes[$template->getName()->value];
                $properties = $this->propertiesHandler->createdFromArrayByTypeDeclaration($template->getProperties(), $nodeType);

                $commands = $commands->withAdditionalCommands(
                    new SetNodeProperties(
                        $parentNode->contentStreamId,
                        $nodeAggregateId = NodeAggregateId::fromParentNodeAggregateIdAndNodeName(
                            $parentNode->nodeAggregateId,
                            $template->getName()
                        ),
                        $parentNode->originDimensionSpacePoint,
                        PropertyValuesToWrite::fromArray(
                            $this->propertiesHandler->requireValidProperties($properties, $caughtExceptions)
                        )
                    ),
                    ...$this->createReferencesCommands(
                        $parentNode->contentStreamId,
                        $nodeAggregateId,
                        $parentNode->originDimensionSpacePoint,
                        $this->propertiesHandler->requireValidReferences($properties, $caughtExceptions)
                    )
                );

                $commands = $this->applyTemplateRecursively(
                    $template->getChildNodes(),
                    $parentNode->forTetheredChildNode(
                        $template->getName(),
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

            try {
                $parentNode->requireConstraintsImposedByAncestorsAreMet($nodeType);
            } catch (NodeConstraintException $nodeConstraintException) {
                $caughtExceptions->add(
                    CaughtException::fromException($nodeConstraintException)
                );
                continue;
            }

            $properties = $this->propertiesHandler->createdFromArrayByTypeDeclaration($template->getProperties(), $nodeType);

            $nodeName = $template->getName() ?? NodeName::fromString(uniqid('node-', false));

            $initialProperties = PropertyValuesToWrite::fromArray(
                $this->propertiesHandler->requireValidProperties($properties, $caughtExceptions)
            );

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
                    $this->propertiesHandler->requireValidReferences($properties, $caughtExceptions)
                )
            );


            $commands = $this->applyTemplateRecursively(
                $template->getChildNodes(),
                $parentNode->forRegularChildNode(
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
     *
     * @param Template|RootTemplate $template
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
