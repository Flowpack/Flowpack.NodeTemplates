<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Behat\Transliterator\Transliterator;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Core\Dimension\ContentDimensionId;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Command\CopyNodesRecursively;
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
use Neos\Flow\I18n\Exception\InvalidLocaleIdentifierException;
use Neos\Flow\I18n\Locale;
use Neos\Neos\Service\TransliterationService;
use Neos\Neos\Ui\Domain\NodeCreation\NodeCreationCommands;

/**
 * Declares the steps how to create a node subtree starting from the root template {@see RootTemplate}
 *
 * The commands can then be handled by the content repository to create the node structure
 *
 * @Flow\Scope("singleton")
 */
class NodeCreationService
{
    /**
     * @Flow\Inject
     * @var TransliterationService
     */
    protected $transliterationService;

    /**
     * @Flow\Inject
     * @var PropertiesProcessor
     */
    protected $propertiesProcessor;

    /**
     * @Flow\Inject
     * @var ReferencesProcessor
     */
    protected $referencesProcessor;

    /**
     * Creates commands {@see NodeCreationCommands} for the root template and its descending configured child node templates.
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeCreationCommands $commands, NodeTypeManager $nodeTypeManager, ContentSubgraphInterface $subgraph, ProcessingErrors $processingErrors): NodeCreationCommands
    {
        $nodeType = $nodeTypeManager->getNodeType($commands->first->nodeTypeName);
        $node = TransientNode::forRegular(
            $commands->first->nodeAggregateId,
            $commands->first->contentStreamId,
            $commands->first->originDimensionSpacePoint,
            $nodeType,
            $commands->first->tetheredDescendantNodeAggregateIds,
            $nodeTypeManager,
            $subgraph,
            $template->getProperties()
        );

        $initialProperties = $commands->first->initialPropertyValues;

        $initialProperties = $initialProperties->merge(
            PropertyValuesToWrite::fromArray(
                $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
            )
        );

        $initialProperties = $this->ensureNodeHasUriPathSegment(
            $nodeType,
            $commands->first->nodeName,
            $commands->first->originDimensionSpacePoint->toDimensionSpacePoint(),
            $initialProperties
        );

        return $this->applyTemplateRecursively(
            $template->getChildNodes(),
            $node,
            $commands->withInitialPropertyValues($initialProperties)->withAdditionalCommands(
                ...$this->createReferencesCommands(
                    $commands->first->contentStreamId,
                    $commands->first->nodeAggregateId,
                    $commands->first->originDimensionSpacePoint,
                    $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
                )
            ),
            $processingErrors
        );
    }

    private function copyTo(Template $template, TransientNode $node)
    {
        if (empty($template->getCopyFrom())) {
            return;
        }

        $sources = is_array($template->getCopyFrom()) ? $template->getCopyFrom() : [$template->getCopyFrom()];

        foreach ($sources as $sourceNode) {
            yield CopyNodesRecursively::createFromSubgraphAndStartNode(
                $node->subgraph,
                $sourceNode,
                $node->originDimensionSpacePoint,
                $node->nodeAggregateId,
                null,
                null
            );
        }

    }

    private function applyTemplateRecursively(Templates $templates, TransientNode $parentNode, NodeCreationCommands $commands, ProcessingErrors $processingErrors): NodeCreationCommands
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->nodeType->hasTetheredNode($template->getName())) {
                /**
                 * Case 1: Auto created child nodes
                 */
                if ($template->getType() !== null) {
                    $processingErrors->add(
                        ProcessingError::fromException(new \RuntimeException(sprintf('Template cant mutate type of auto created child nodes. Got: "%s"', $template->getType()->value), 1685999829307))
                    );
                    // we continue processing the node
                }

                $node = $parentNode->forTetheredChildNode(
                    $template->getName(),
                    $template->getProperties()
                );

                $commands = $commands->withAdditionalCommands(
                    SetNodeProperties::create(
                        $parentNode->contentStreamId,
                        $node->nodeAggregateId,
                        $parentNode->originDimensionSpacePoint,
                        PropertyValuesToWrite::fromArray(
                            $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
                        )
                    ),
                    ...$this->createReferencesCommands(
                        $parentNode->contentStreamId,
                        $node->nodeAggregateId,
                        $parentNode->originDimensionSpacePoint,
                        $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
                    ),
                    ...iterator_to_array($this->copyTo($template, $node))
                );

                $commands = $this->applyTemplateRecursively(
                    $template->getChildNodes(),
                    $node,
                    $commands,
                    $processingErrors
                );
                continue;
            }

            /**
             * Case 2: Regular to be created nodes (non auto-created nodes)
             */
            if ($template->getType() === null) {
                $processingErrors->add(
                    ProcessingError::fromException(new \RuntimeException(sprintf('Template requires type to be set for non auto created child nodes.'), 1685999829307))
                );
                continue;
            }
            if (!$parentNode->nodeTypeManager->hasNodeType($template->getType())) {
                $processingErrors->add(
                    ProcessingError::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->value), 1685999795564))
                );
                continue;
            }

            $nodeType = $parentNode->nodeTypeManager->getNodeType($template->getType());

            if ($nodeType->isAbstract()) {
                $processingErrors->add(
                    ProcessingError::fromException(new \RuntimeException(sprintf('Template requires type to be a non abstract NodeType. Got: "%s".', $template->getType()->value), 1686417628976))
                );
                continue;
            }

            try {
                $parentNode->requireConstraintsImposedByAncestorsToBeMet($nodeType);
            } catch (NodeConstraintException $nodeConstraintException) {
                $processingErrors->add(
                    ProcessingError::fromException($nodeConstraintException)
                );
                continue;
            }

            $node = $parentNode->forRegularChildNode(NodeAggregateId::create(), $nodeType, $template->getProperties());

            $nodeName = $template->getName() ?? NodeName::fromString(uniqid('node-', false));

            $initialProperties = PropertyValuesToWrite::fromArray(
                $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
            );

            $initialProperties = $this->ensureNodeHasUriPathSegment(
                $nodeType,
                $nodeName,
                $parentNode->originDimensionSpacePoint->toDimensionSpacePoint(),
                $initialProperties
            );

            $commands = $commands->withAdditionalCommands(
                CreateNodeAggregateWithNode::create(
                    $parentNode->contentStreamId,
                    $node->nodeAggregateId,
                    $template->getType(),
                    $parentNode->originDimensionSpacePoint,
                    $parentNode->nodeAggregateId,
                    nodeName: $nodeName,
                    initialPropertyValues: $initialProperties
                )->withTetheredDescendantNodeAggregateIds($node->tetheredNodeAggregateIds),
                ...$this->createReferencesCommands(
                    $parentNode->contentStreamId,
                    $node->nodeAggregateId,
                    $parentNode->originDimensionSpacePoint,
                    $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
                ),
                // todo ...iterator_to_array($this->copyTo($template, $node))
            );


            $commands = $this->applyTemplateRecursively(
                $template->getChildNodes(),
                $node,
                $commands,
                $processingErrors
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
            $commands[] = SetNodeReferences::create(
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
     * All document node types get a uri path segment; if it is not explicitly set in the properties,
     * it should be built based on the title property
     */
    private function ensureNodeHasUriPathSegment(
        NodeType $nodeType,
        ?NodeName $nodeName,
        DimensionSpacePoint $dimensionSpacePoint,
        PropertyValuesToWrite $propertiesToWrite
    ): PropertyValuesToWrite {
        if (!$nodeType->isOfType('Neos.Neos:Document')) {
            return $propertiesToWrite;
        }
        if (isset($propertiesToWrite->values['uriPathSegment'])) {
            return $propertiesToWrite;
        }

        return $propertiesToWrite->withValue(
            'uriPathSegment',
            $this->generateUriPathSegment(
                $dimensionSpacePoint,
                $propertiesToWrite->values['title'] ?? $nodeName?->value ?? uniqid('', true)
            )
        );
    }

    /**
     * Copied from https://github.com/neos/neos-ui/blob/6929f73ffc74b1c7b63fbf80b5c2b3152e443534/Classes/NodeCreationHandler/DocumentTitleNodeCreationHandler.php#L80
     *
     * The {@see \Neos\Neos\Utility\NodeUriPathSegmentGenerator::generateUriPathSegment()} only works with whole Nodes.
     *
     * Duplicated code might be cleaned up via https://github.com/neos/neos-development-collection/pull/4324
     */
    private function generateUriPathSegment(DimensionSpacePoint $dimensionSpacePoint, string $text): string
    {
        $languageDimensionValue = $dimensionSpacePoint->getCoordinate(new ContentDimensionId('language'));
        if ($languageDimensionValue !== null) {
            try {
                $language = (new Locale($languageDimensionValue))->getLanguage();
            } catch (InvalidLocaleIdentifierException $e) {
                // we don't need to do anything here; we'll just transliterate the text.
            }
        }
        $transliterated = $this->transliterationService->transliterate($text, $language ?? null);

        return Transliterator::urlize($transliterated);
    }
}
