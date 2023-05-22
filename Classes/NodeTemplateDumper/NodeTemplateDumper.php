<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeTemplateDumper;

use Neos\ContentRepository\Domain\Model\ArrayPropertyCollection;
use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\Flow\Annotations as Flow;
use Symfony\Component\Yaml\Yaml;

/** @Flow\Scope("singleton") */
class NodeTemplateDumper
{
    /**
     * @Flow\Inject
     * @var CommentService
     */
    protected $commentService;

    /**
     * Dump the node tree structure into a NodeTemplate Yaml structure.
     * References to Nodes and non-primitive property values are commented out in the Yaml.
     *
     * @param Node|NodeInterface|TraversableNodeInterface $node specified root node of the node tree to dump
     * @return string yaml representation of the node template
     */
    public function createNodeTemplateYamlDumpFromSubtree(Node $node): string
    {
        $nodeType = $node->getNodeType();
        if ($nodeType->isOfType('Neos.Neos:Document')) {
            $template = $this->nodeTemplateFromDocumentNodes([$node]);
        } elseif ($nodeType->isOfType('Neos.Neos:Content') || $nodeType->isOfType('Neos.Neos:ContentCollection')) {
            $template = $this->nodeTemplateFromContentNodes([$node]);
        } else {
            throw new \InvalidArgumentException("Node {$node->getIdentifier()} must be one of Neos.Neos:Document,Neos.Neos:Content,Neos.Neos:ContentCollection.");
        }

        $templateInContext = [
            "Your.NodeType" => [
                "options" => [
                    "template" => [
                        "childNodes" => $template
                    ]
                ]
            ]
        ];

        $yaml = Yaml::dump($templateInContext, 99, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_NULL_AS_TILDE);

        $yamlWithComments = $this->commentService->renderCommentsInYamlDump($yaml);

        return $yamlWithComments;
    }

    /** @param array<Node|NodeInterface> $nodes */
    private function nodeTemplateFromDocumentNodes(array $nodes): array
    {
        $template = [];
        foreach ($nodes as $index => $node) {
            assert($node instanceof Node);

            if ($node->isTethered()) {
                throw new \Exception("@todo");
            }

            $template["page$index"] = array_filter([
                "name" => "page-$index",
                "type" => $node->getNodeType()->getName(),
                "properties" => $this->nonDefaultConfiguredNodeProperties($node),
                "childNodes" => array_merge(
                    $this::nodeTemplateFromContentNodes(
                        $node->getChildNodes("Neos.Neos:Content,Neos.Neos:ContentCollection")
                    ),
                    $this::nodeTemplateFromDocumentNodes($node->getChildNodes("Neos.Neos:Document")),
                )
            ]);
        }

        return $template;
    }

    private function nonDefaultConfiguredNodeProperties(Node $node): array
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
                array_key_exists("defaultValue", $configuration)
                && $configuration["defaultValue"] === $nodeProperties[$propertyName]
            ) {
                // node property is the same as default
                continue;
            }

            $propertyValue = $nodeProperties[$propertyName];
            if ($propertyValue === null || $propertyValue === []) {
                continue;
            }
            if (is_string($propertyValue) && trim($propertyValue) === "") {
                continue;
            }

            if ($dataSourceIdentifier = $configuration["ui"]["inspector"]["editorOptions"]["dataSourceIdentifier"] ?? null) {
                $filteredProperties[$propertyName] = $this->commentService->serialize(
                    function ($indentation, $propertyName) use ($dataSourceIdentifier, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> Datasource "' . $dataSourceIdentifier . '" with value ' . $this->valueToDebugString($propertyValue);
                    }
                );
                continue;
            }

            if (($configuration["type"] ?? null) === "reference") {
                $nodeTypesInReference = $configuration["ui"]["inspector"]["editorOptions"]["nodeTypes"] ?? ["Neos.Neos:Document"];
                $filteredProperties[$propertyName] = $this->commentService->serialize(
                    function ($indentation, $propertyName) use ($nodeTypesInReference, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> Reference of NodeTypes (' . join(", ", $nodeTypesInReference) . ') with value ' . $this->valueToDebugString($propertyValue);
                    }
                );
                continue;
            }

            if (($configuration["ui"]["inspector"]["editor"] ?? null) === 'Neos.Neos/Inspector/Editors/SelectBoxEditor') {
                $selectBoxValues = array_keys($configuration["ui"]["inspector"]["editorOptions"]["values"] ?? []);
                $filteredProperties[$propertyName] = $this->commentService->serialize(
                    function ($indentation, $propertyName) use ($selectBoxValues, $propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> SelectBox of ' . mb_strimwidth(json_encode($selectBoxValues), 0, 60, " ...]") . ' with value ' . $this->valueToDebugString($propertyValue);
                    }
                );
                continue;
            }

            if (is_object($propertyValue) || (is_array($propertyValue) && is_object(array_values($propertyValue)[0] ?? null))) {
                $filteredProperties[$propertyName] = $this->commentService->serialize(
                    function ($indentation, $propertyName) use ($propertyValue) {
                        return $indentation . '# ' . $propertyName . ' -> ' . $this->valueToDebugString($propertyValue);
                    }
                );
                continue;
            }

            $filteredProperties[$propertyName] = $propertyValue;
        }

        return $filteredProperties;
    }

    private function valueToDebugString($value): string
    {
        if ($value instanceof Node) {
            return 'Node(' . $value->getIdentifier() . ')';
        }
        if (is_iterable($value)) {
            $name = null;
            $entries = [];
            foreach ($value as $key => $item) {
                if ($item instanceof Node) {
                    if ($name === null || $name === 'Nodes') {
                        $name = 'Nodes';
                    } else {
                        $name = 'array';
                    }
                    $name = $name === null ;
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

    /** @param array<Node|NodeInterface> $nodes */
    private function nodeTemplateFromContentNodes(array $nodes): array
    {
        $template = [];
        foreach ($nodes as $index => $node) {
            assert($node instanceof Node);

            $templatePart = array_filter([
                "properties" => $this->nonDefaultConfiguredNodeProperties($node),
                "childNodes" => $this->nodeTemplateFromContentNodes($node->getChildNodes("Neos.Neos:Content,Neos.Neos:ContentCollection"))
            ]);

            if ($templatePart === []) {
                continue;
            }

            if ($node->isTethered()) {

                $readableId = [
                    "main" => "mainContentCollection",
                    "footer" => "footerContentCollection"
                ][$node->getName()] ?? $node->getName() . 'Tethered';

                $template[$readableId] = array_merge([
                    "name" => $node->getName()
                ], $templatePart);
                continue;
            }

            $template["content$index"] = array_merge([
                "type" => $node->getNodeType()->getName()
            ], $templatePart);
        }

        return $template;
    }
}
