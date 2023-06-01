<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeTemplateDumper;

use Neos\ContentRepository\Domain\Model\ArrayPropertyCollection;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\EelHelper\TranslationHelper;
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
     * Dump the node tree structure into a NodeTemplate YAML structure.
     * References to Nodes and non-primitive property values are commented out in the YAML.
     *
     * @param NodeInterface $startingNode specified root node of the node tree to dump
     * @return string YAML representation of the node template
     */
    public function createNodeTemplateYamlDumpFromSubtree(NodeInterface $startingNode): string
    {
        $comments = Comments::empty();

        $nodeType = $startingNode->getNodeType();

        if (
            !$nodeType->isOfType('Neos.Neos:Document')
            && !$nodeType->isOfType('Neos.Neos:Content')
            && !$nodeType->isOfType('Neos.Neos:ContentCollection')
        ) {
            throw new \InvalidArgumentException("Node {$startingNode->getIdentifier()} must be one of Neos.Neos:Document,Neos.Neos:Content,Neos.Neos:ContentCollection.");
        }

        $template = $this->nodeTemplateFromNodes([$startingNode], $comments);

        foreach ($template as $firstEntry) {
            break;
        }
        assert(isset($firstEntry));

        $templateRoot = [
            'template' => array_filter([
                'properties' => $firstEntry['properties'] ?? null,
                'childNodes' => $firstEntry['childNodes'] ?? null,
            ])
        ];

        $yaml = Yaml::dump($templateRoot, 99, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NULL_AS_TILDE);

        $yamlWithComments = $comments->renderCommentsInYamlDump($yaml);

        return $yamlWithComments;
    }

    /** @param array<NodeInterface> $nodes */
    private function nodeTemplateFromNodes(array $nodes, Comments $comments): array
    {
        $documentNodeTemplates = [];
        $contentNodeTemplates = [];
        foreach ($nodes as $index => $node) {
            assert($node instanceof NodeInterface);
            $nodeType = $node->getNodeType();
            $isDocumentNode = $nodeType->isOfType('Neos.Neos:Document');

            $templatePart = array_filter([
                'properties' => $this->nonDefaultConfiguredNodeProperties($node, $comments),
                'childNodes' => $this->nodeTemplateFromNodes(
                    $isDocumentNode
                        ? $node->getChildNodes('Neos.Neos:Content,Neos.Neos:ContentCollection,Neos.Neos:Document')
                        : $node->getChildNodes('Neos.Neos:Content,Neos.Neos:ContentCollection'),
                    $comments
                )
            ]);

            if ($templatePart === []) {
                continue;
            }

            if ($isDocumentNode) {
                if ($node->isTethered()) {
                    $documentNodeTemplates[$node->getLabel() ?: $node->getName()] = array_merge([
                        'name' => $node->getName()
                    ], $templatePart);
                    continue;
                }

                $documentNodeTemplates["page$index"] = array_merge([
                    'type' => $node->getNodeType()->getName()
                ], $templatePart);
                continue;
            }

            if ($node->isTethered()) {
                $contentNodeTemplates[$node->getLabel() ?: $node->getName()] = array_merge([
                    'name' => $node->getName()
                ], $templatePart);
                continue;
            }

            $contentNodeTemplates["content$index"] = array_merge([
                'type' => $node->getNodeType()->getName()
            ], $templatePart);
        }

        return array_merge($contentNodeTemplates, $documentNodeTemplates);
    }

    private function nonDefaultConfiguredNodeProperties(NodeInterface $node, Comments $comments): array
    {
        $nodeType = $node->getNodeType();
        $nodeProperties = $node->getProperties();

        $filteredProperties = [];
        foreach ($nodeType->getProperties() as $propertyName => $configuration) {
            if (
                $nodeProperties instanceof ArrayPropertyCollection
                    ? !$nodeProperties->offsetExists($propertyName)
                    : !array_key_exists($propertyName, $nodeProperties)
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
                    function ($indentation, $propertyName) use($comment, $propertyValue, $label) {
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

            if (($configuration['type'] ?? null) === 'reference') {
                $nodeTypesInReference = $configuration['ui']['inspector']['editorOptions']['nodeTypes'] ?? ['Neos.Neos:Document'];
                $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($nodeTypesInReference, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> Reference of NodeTypes (' . join(', ', $nodeTypesInReference) . ') with value ' . $this->valueToDebugString($propertyValue);
                    }
                )));
                continue;
            }

            if (($configuration['ui']['inspector']['editor'] ?? null) === 'Neos.Neos/Inspector/Editors/SelectBoxEditor') {
                $selectBoxValues = array_keys($configuration['ui']['inspector']['editorOptions']['values'] ?? []);
                $filteredProperties[$propertyName] = $comments->addCommentAndGetMarker($augmentCommentWithLabel(Comment::fromRenderer(
                    function ($indentation, $propertyName) use ($selectBoxValues, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> SelectBox of '
                            . mb_strimwidth(json_encode($selectBoxValues), 0, 60, ' ...]')
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

        return $filteredProperties;
    }

    private function valueToDebugString($value): string
    {
        if ($value instanceof NodeInterface) {
            return 'Node(' . $value->getIdentifier() . ')';
        }
        if (is_iterable($value)) {
            $name = null;
            $entries = [];
            foreach ($value as $key => $item) {
                if ($item instanceof NodeInterface) {
                    if ($name === null || $name === 'Nodes') {
                        $name = 'Nodes';
                    } else {
                        $name = 'array';
                    }
                    $entries[$key] = $item->getIdentifier();
                    continue;
                }
                $name = 'array';
                $entries[$key] = is_object($item) ? get_class($item) : json_encode($item);
            }
            return $name . '(' . join(', ', $entries) . ')';
        }

        if (is_object($value)) {
            return 'object(' . get_class($value) . ')';
        }
        return json_encode($value);
    }
}
