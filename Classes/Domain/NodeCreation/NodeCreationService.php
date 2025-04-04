<?php

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use Behat\Transliterator\Transliterator;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingError;
use Flowpack\NodeTemplates\Domain\ErrorHandling\ProcessingErrors;
use Flowpack\NodeTemplates\Domain\Template\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template\Templates;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\Dimension\ContentDimensionId;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesForName;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Node\PropertyName;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
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
    public function apply(RootTemplate $template, NodeCreationCommands $commands, NodeTypeManager $nodeTypeManager, ContentSubgraphInterface $subgraph, NodeType $nodeType, ProcessingErrors $processingErrors): NodeCreationCommands
    {
        $node = TransientNode::forRegular(
            $commands->first->nodeAggregateId,
            $commands->first->workspaceName,
            $commands->first->originDimensionSpacePoint,
            $nodeType,
            $commands->first->tetheredDescendantNodeAggregateIds,
            $nodeTypeManager,
            $subgraph,
            $template->getProperties()
        );

        $propertyValuesToWrite = PropertyValuesToWrite::fromArray(
            $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
        );

        if (count($defaultPropertiesToUnset = iterator_to_array($propertyValuesToWrite->getPropertiesToUnset()))) {
            // FIXME workaround for https://github.com/neos/neos-development-collection/issues/5154
            $setDefaultPropertiesToNull = SetNodeProperties::create(
                $commands->first->workspaceName,
                $commands->first->nodeAggregateId,
                $commands->first->originDimensionSpacePoint,
                PropertyValuesToWrite::fromArray(
                    array_fill_keys(array_map(fn (PropertyName $name) => $name->value, $defaultPropertiesToUnset), null)
                )
            );
            $commands = $commands->withAdditionalCommands(Commands::create($setDefaultPropertiesToNull));
        }

        $initialProperties = $commands->first->initialPropertyValues;

        $initialProperties = $initialProperties->merge($propertyValuesToWrite);

        $initialProperties = $this->ensureNodeHasUriPathSegment(
            $nodeType,
            $commands->first->nodeName,
            $commands->first->originDimensionSpacePoint->toDimensionSpacePoint(),
            $initialProperties
        );

        $commands = $commands->withInitialPropertyValues($initialProperties);
        $setReferences = $this->createReferencesCommand(
            $commands->first->workspaceName,
            $commands->first->nodeAggregateId,
            $commands->first->originDimensionSpacePoint,
            $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
        );
        if ($setReferences) {
            $commands = $commands->withInitialReferences($setReferences->references);
        }

        $childNodeCommands = $this->applyTemplateRecursively(
            $template->getChildNodes(),
            $node,
            Commands::createEmpty(),
            $processingErrors
        );

        return $commands->withAdditionalCommands($childNodeCommands);
    }

    private function applyTemplateRecursively(Templates $templates, TransientNode $parentNode, Commands $commands, ProcessingErrors $processingErrors): Commands
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->nodeType->tetheredNodeTypeDefinitions->contain($template->getName())) {
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

                $propertiesToWrite = PropertyValuesToWrite::fromArray(
                    $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
                );

                $commands = $commands->merge(Commands::fromArray(array_filter([
                    $propertiesToWrite->isEmpty() ? null : SetNodeProperties::create(
                        $parentNode->workspaceName,
                        $node->aggregateId,
                        $parentNode->originDimensionSpacePoint,
                        $propertiesToWrite
                    ),
                    $this->createReferencesCommand(
                        $parentNode->workspaceName,
                        $node->aggregateId,
                        $parentNode->originDimensionSpacePoint,
                        $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
                    )
                ])));

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

            $nodeType = $parentNode->nodeTypeManager->getNodeType($template->getType());
            if (!$nodeType) {
                $processingErrors->add(
                    ProcessingError::fromException(new \RuntimeException(sprintf('Template requires type to be a valid NodeType. Got: "%s".', $template->getType()->value), 1685999795564))
                );
                continue;
            }

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

            $nodeName = $template->getName();

            $initialProperties = PropertyValuesToWrite::fromArray(
                $this->propertiesProcessor->processAndValidateProperties($node, $processingErrors)
            );

            $initialProperties = $this->ensureNodeHasUriPathSegment(
                $nodeType,
                $nodeName,
                $parentNode->originDimensionSpacePoint->toDimensionSpacePoint(),
                $initialProperties
            );

            $createNode = CreateNodeAggregateWithNode::create(
                $parentNode->workspaceName,
                $node->aggregateId,
                $template->getType(),
                $parentNode->originDimensionSpacePoint,
                $parentNode->aggregateId,
                initialPropertyValues: $initialProperties
            )->withTetheredDescendantNodeAggregateIds($node->tetheredNodeAggregateIds);
            if ($nodeName) {
                $createNode = $createNode->withNodeName($nodeName);
            }

            $commands = $commands->merge(Commands::fromArray(array_filter([
                $createNode,
                $this->createReferencesCommand(
                    $parentNode->workspaceName,
                    $node->aggregateId,
                    $parentNode->originDimensionSpacePoint,
                    $this->referencesProcessor->processAndValidateReferences($node, $processingErrors)
                )
            ])));

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
     */
    private function createReferencesCommand(
        WorkspaceName $workspaceName,
        NodeAggregateId $nodeAggregateId,
        OriginDimensionSpacePoint $originDimensionSpacePoint,
        array $references
    ): ?SetNodeReferences {
        $referencesForName = [];
        foreach ($references as $name => $nodeAggregateIds) {
            $referencesForName[] = NodeReferencesForName::fromTargets(
                ReferenceName::fromString($name),
                $nodeAggregateIds,
            );
        }
        return empty($referencesForName)
            ? null
            : SetNodeReferences::create(
                $workspaceName,
                $nodeAggregateId,
                $originDimensionSpacePoint,
                NodeReferencesToWrite::create(...$referencesForName)
            );
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
