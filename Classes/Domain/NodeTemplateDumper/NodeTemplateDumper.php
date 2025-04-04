<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeTemplateDumper;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\Nodes;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
use Neos\Neos\Domain\NodeLabel\NodeLabelGeneratorInterface;
use Symfony\Component\Yaml\Yaml;

/** @Flow\Scope("singleton") */
class NodeTemplateDumper
{
    /**
     * @var TranslationHelper
     * @Flow\Inject
     */
    protected $translationHelper;

    /**
     * @var NodeLabelGeneratorInterface
     * @Flow\Inject
     */
    protected $nodeLabelGenerator;

    /**
     * Dump the node tree structure into a NodeTemplate YAML structure.
     * References to Nodes and non-primitive property values are commented out in the YAML.
     *
     * @param Node $startingNode specified root node of the node tree to dump
     * @return string YAML representation of the node template
     */
    public function createNodeTemplateYamlDumpFromSubtree(Node $startingNode, ContentRepository $contentRepository): string
    {
        $comments = Comments::empty();

        $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($startingNode->nodeTypeName);

        if (
            !$nodeType || (
                !$nodeType->isOfType('Neos.Neos:Document')
                && !$nodeType->isOfType('Neos.Neos:Content')
                && !$nodeType->isOfType('Neos.Neos:ContentCollection')
            )
        ) {
            throw new \InvalidArgumentException("Node {$startingNode->aggregateId->value} must be one of Neos.Neos:Document,Neos.Neos:Content,Neos.Neos:ContentCollection.");
        }

        $template = $this->nodeTemplateFromNodes(Nodes::fromArray([$startingNode]), $comments, $contentRepository);

        $firstEntry = null;
        foreach ($template as $firstEntry) {
            break;
        }

        $properties = $firstEntry['properties'] ?? null;
        $childNodes = $firstEntry['childNodes'] ?? null;


        $templateInNodeTypeOptions = [
            $nodeType->name->value => [
                'options' => [
                    'template' => array_filter([
                        'properties' => $properties,
                        'childNodes' => $childNodes
                    ])
                ]
            ]
        ];

        $yamlWithSerializedComments = Yaml::dump($templateInNodeTypeOptions, 99, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NULL_AS_TILDE);

        return $comments->renderCommentsInYamlDump($yamlWithSerializedComments);
    }

    /** @return array<string, array<string, mixed>> */
    private function nodeTemplateFromNodes(Nodes $nodes, Comments $comments, ContentRepository $contentRepository): array
    {
        $subgraph = null;

        $documentNodeTemplates = [];
        $contentNodeTemplates = [];
        foreach ($nodes as $index => $node) {
            $subgraph ??= $contentRepository->getContentGraph($node->workspaceName)->getSubgraph(
                $node->dimensionSpacePoint,
                $node->visibilityConstraints
            );

            $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($node->nodeTypeName);
            if (!$nodeType) {
                throw new \RuntimeException("NodeType {$node->nodeTypeName->value} of Node {$node->aggregateId->value} doesnt exist.");
            }

            $isDocumentNode = $nodeType->isOfType('Neos.Neos:Document');

            $templatePart = array_filter([
                'properties' => $this->nonDefaultConfiguredNodeProperties($node, $nodeType, $comments, $subgraph),
                'childNodes' => $this->nodeTemplateFromNodes(
                    $subgraph->findChildNodes($node->aggregateId, FindChildNodesFilter::create('Neos.Neos:Node')),
                    $comments,
                    $contentRepository
                )
            ]);

            if ($templatePart === []) {
                continue;
            }

            if ($isDocumentNode) {
                if ($node->classification->isTethered()) {
                    $tetheredName = $node->name;
                    assert($tetheredName !== null);

                    $documentNodeTemplates[$this->nodeLabelGenerator->getLabel($node) ?: $tetheredName->value] = array_merge([
                        'name' => $tetheredName->value
                    ], $templatePart);
                    continue;
                }

                $documentNodeTemplates["page$index"] = array_merge([
                    'type' => $node->nodeTypeName->value
                ], $templatePart);
                continue;
            }

            if ($node->classification->isTethered()) {
                $tetheredName = $node->name;
                assert($tetheredName !== null);

                $contentNodeTemplates[$this->nodeLabelGenerator->getLabel($node) ?: $tetheredName->value] = array_merge([
                    'name' => $tetheredName->value
                ], $templatePart);
                continue;
            }

            $contentNodeTemplates["content$index"] = array_merge([
                'type' => $node->nodeTypeName->value
            ], $templatePart);
        }

        return array_merge($contentNodeTemplates, $documentNodeTemplates);
    }

    /** @return array<string, string> */
    private function nonDefaultConfiguredNodeProperties(Node $node, NodeType $nodeType, Comments $comments, ContentSubgraphInterface $subgraph): array
    {
        $nodeProperties = $node->properties;

        $filteredProperties = [];
        foreach ($nodeType->getProperties() as $propertyName => $configuration) {
            if (
                !$nodeProperties->offsetExists($propertyName)
            ) {
                // node doesn't have the property set
                continue;
            }

            if (
                array_key_exists('defaultValue', $configuration)
                && $configuration['defaultValue'] === $nodeProperties[$propertyName]
            ) {
                // node property is the same as default
                continue;
            }

            $propertyValue = $nodeProperties[$propertyName];
            if ($propertyValue === null || $propertyValue === []) {
                continue;
            }
            if (is_string($propertyValue) && trim($propertyValue) === '') {
                continue;
            }

            $label = $configuration['ui']['label'] ?? null;
            $augmentCommentWithLabel = fn (Comment $comment) => $comment;
            if ($label) {
                $label = $this->translationHelper->translate($label);
                $augmentCommentWithLabel = fn (Comment $comment) => Comment::fromRenderer(
                    function ($indentation, $propertyName) use($comment, $label) {
                        return $indentation . '# ' . $label . "\n" .
                            $comment->toYamlComment($indentation, $propertyName);
                    }
                );
            }

            if ($dataSourceIdentifier = $configuration['ui']['inspector']['editorOptions']['dataSourceIdentifier'] ?? null) {
                $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($dataSourceIdentifier, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> Datasource "' . $dataSourceIdentifier . '" with value ' . $this->valueToDebugString($propertyValue);
                    }
                )));
                continue;
            }

            if (($configuration['ui']['inspector']['editor'] ?? null) === 'Neos.Neos/Inspector/Editors/SelectBoxEditor') {
                $selectBoxValues = array_keys($configuration['ui']['inspector']['editorOptions']['values'] ?? []);
                $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($selectBoxValues, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> SelectBox of '
                            . mb_strimwidth(json_encode($selectBoxValues, JSON_THROW_ON_ERROR), 0, 60, ' ...]')
                            . ' with value ' . $this->valueToDebugString($propertyValue);
                    }
                )));
                continue;
            }

            if (is_object($propertyValue) || (is_array($propertyValue) && is_object(array_values($propertyValue)[0] ?? null))) {
                $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> ' . $this->valueToDebugString($propertyValue);
                    }
                )));
                continue;
            }

            $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                function ($indentation, $propertyName) use ($propertyValue) {
                    return $indentation . $propertyName . ': ' . Yaml::dump($propertyValue);
                }
            )));
        }

        if ($nodeType->getReferences() === []) {
            return $filteredProperties;
        }

        $references = $subgraph->findReferences($node->aggregateId, FindReferencesFilter::create());
        $referencesArray = [];
        foreach ($references as $reference) {
            if (!isset($referencesArray[$reference->name->value])) {
                $referencesArray[$reference->name->value] = $reference->node->aggregateId->value;
                continue;
            }
            $referencesArray[$reference->name->value] .= ', ' . $reference->node->aggregateId->value;
        }

        foreach ($nodeType->getReferences() as $referenceName => $configuration) {
            $referenceValue = $referencesArray[$referenceName] ?? null;
            if (!$referenceValue) {
                continue;
            }

            $label = $configuration['ui']['label'] ?? null;
            $augmentCommentWithLabel = fn (Comment $comment) => $comment;
            if ($label) {
                $label = $this->translationHelper->translate($label);
                $augmentCommentWithLabel = fn (Comment $comment) => Comment::fromRenderer(
                    function ($indentation, $propertyName) use($comment, $label) {
                        return $indentation . '# ' . $label . "\n" .
                            $comment->toYamlComment($indentation, $propertyName);
                    }
                );
            }

            if (($configuration['constraints']['maxItems'] ?? null) === 1) {
                $nodeTypesInReference = $configuration['ui']['inspector']['editorOptions']['nodeTypes'] ?? ['Neos.Neos:Document'];
                $filteredProperties[$referenceName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($nodeTypesInReference, $referenceValue) {
                        return $indentation . '# ' . $propertyName . ' -> Reference of NodeTypes (' . join(', ', $nodeTypesInReference) . ') with Node: ' . $referenceValue;
                    }
                )));
                continue;
            }

            $filteredProperties[$referenceName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                function ($indentation, $propertyName) use ($referenceValue) {
                    return $indentation . '# ' . $propertyName . ' -> References with Nodes: ' . $referenceValue;
                }
            )));
        }

        return $filteredProperties;
    }

    private function valueToDebugString(mixed $value): string
    {
        if (is_iterable($value)) {
            $entries = [];
            foreach ($value as $key => $item) {
                $entries[$key] = is_object($item) ? get_class($item) : json_encode($item);
            }
            return 'array(' . join(', ', $entries) . ')';
        }

        if (is_object($value)) {
            return 'object(' . get_class($value) . ')';
        }
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
