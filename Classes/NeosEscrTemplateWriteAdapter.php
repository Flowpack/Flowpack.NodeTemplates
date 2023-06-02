<?php
namespace Flowpack\NodeTemplates;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;

/**
 * TODO To use it replace the line https://github.com/neos/neos-ui/blob/a7ab78f006320ec6b58be106a5451cd24be075ac/Classes/Domain/Model/Changes/AbstractCreate.php#L118 with
 * `(new NeosEscrTemplateWriteAdapter())->writeTemplate($command, $contentRepository);`
 */
class NeosEscrTemplateWriteAdapter
{
    public function writeTemplate(CreateNodeAggregateWithNode $createNodeAggregateWithNode, ContentRepository $contentRepository): void
    {
        $template = [
            'childNodes' => [
                'main' => [
                    'name' => 'main',
                    'childNodes' => [
                        'content1' => [
                            'type' => "Neos.Demo:Content.Text",
                            'properties' => [
                                'text' => "huhu",
                            ]
                        ]
                    ]
                ],
                'foo' => [
                    'type' => 'Neos.Demo:Document.Page',
                    'childNodes' => [
                        'main' => [
                            'name' => 'main',
                            'childNodes' => [
                                'content1' => [
                                    'type' => "Neos.Demo:Content.Text",
                                    'properties' => [
                                        'text' => "textiii"
                                    ]
                                ],
                                'content2' => [
                                    'type' => "Neos.Demo:Content.Text",
                                    'properties' => [
                                        'text' => "huijkuihjnihujbn"
                                    ]
                                ]
                            ]
                        ],
                    ]
                ]
            ]
        ];

        $createNodeCommand = $this->augmentWithTetheredDescendantNodeAggregateIds($createNodeAggregateWithNode, $contentRepository);

        if (isset($template['properties'])) {
            // documents generate uripath blabla
            $createNodeCommand = $createNodeCommand->initialPropertyValues->merge(
                PropertyValuesToWrite::fromArray($this->requireValidProperties($template['properties']))
            );
        }

        $commands = $this->createCommandsRecursivelyFromTemplateChildNodes($createNodeCommand, $template, $contentRepository);

        $contentRepository->handle($createNodeCommand)->block();

        foreach ($commands as $command) {
            $contentRepository->handle($command)->block();
        }
    }

    /**
     * In the old CR, it was common practice to set internal or meta properties via this syntax: `_hidden` so it was also allowed in templates but not anymore.
     * @throws \InvalidArgumentException
     */
    private function requireValidProperties(array $properties): array
    {
        $legacyInternalProperties = [
            '_accessRoles',
            '_contentObject',
            '_hidden',
            '_hiddenAfterDateTime',
            '_hiddenBeforeDateTime',
            '_hiddenInIndex',
            '_index',
            '_name',
            '_nodeType',
            '_removed',
            '_workspace'
        ];
        foreach ($properties as $propertyName => $propertyValue) {
            if (str_starts_with($propertyName, '_')) {
                $lowerPropertyName = strtolower($propertyName);
                foreach ($legacyInternalProperties as $legacyInternalProperty) {
                    if ($lowerPropertyName === strtolower($legacyInternalProperty)) {
                        throw new \InvalidArgumentException('Internal legacy properties are not implement.' . $propertyName);
                    }
                }
            }
        }
        return $properties;
    }

    private function createCommandsRecursivelyFromTemplateChildNodes(CreateNodeAggregateWithNode $createParentNodeCommand, array $template, ContentRepository $contentRepository): array
    {
        $makeCreateNodeCommand = function (NodeAggregateId $parentNodeAggregateId, array $subTemplate) use ($createParentNodeCommand, $contentRepository) {
            return $this->augmentWithTetheredDescendantNodeAggregateIds(new CreateNodeAggregateWithNode(
                contentStreamId: $createParentNodeCommand->contentStreamId,
                nodeAggregateId: NodeAggregateId::create(),
                nodeTypeName: NodeTypeName::fromString($subTemplate['type']),
                originDimensionSpacePoint: $createParentNodeCommand->originDimensionSpacePoint,
                parentNodeAggregateId: $parentNodeAggregateId,
                nodeName: NodeName::fromString(uniqid('node-', false)),
                initialPropertyValues: isset($subTemplate['properties'])
                    ? PropertyValuesToWrite::fromArray($this->requireValidProperties($subTemplate['properties']))
                    : null
            ), $contentRepository);
        };

        $commands = [];
        foreach ($template['childNodes'] ?? [] as $childNode) {
            if (isset($childNode['name']) && $autoCreatedNodeId = $createParentNodeCommand->tetheredDescendantNodeAggregateIds->getNodeAggregateId(NodePath::fromString($childNode['name']))) {
                if (isset($childNode['type'])) {
                    throw new \Exception('For auto-created nodes the type cannot be qualified.');
                }
                if (isset($childNode['properties'])) {
                    $commands[] = new SetNodeProperties(
                        $createParentNodeCommand->contentStreamId,
                        $autoCreatedNodeId,
                        $createParentNodeCommand->originDimensionSpacePoint,
                        PropertyValuesToWrite::fromArray($this->requireValidProperties($childNode['properties']))
                    );
                }
                foreach ($childNode['childNodes'] ?? [] as $innerChildNode) {
                    $commands[] = $newParent = $makeCreateNodeCommand($autoCreatedNodeId, $innerChildNode);
                    $commands = [...$commands, ...$this->createCommandsRecursivelyFromTemplateChildNodes($newParent, $innerChildNode, $contentRepository)];
                }
            } else {
                // if is document setUriPath based on title
                $commands[] = $newParent = $makeCreateNodeCommand($createParentNodeCommand->nodeAggregateId, $childNode);
                $commands = [...$commands, ...$this->createCommandsRecursivelyFromTemplateChildNodes($newParent, $childNode, $contentRepository)];
            }
        }
        return $commands;
    }

    /**
     * Precalculate the nodeIds for the auto-created childNodes, so that we can determine the id beforehand and use it for succeeding operations.
     *
     * ```
     * $mainContentCollectionNodeId = $createNodeAggregateWithNode->tetheredDescendantNodeAggregateIds->getNodeAggregateId(NodePath::fromString('main'))
     * ```
     */
    private function augmentWithTetheredDescendantNodeAggregateIds(CreateNodeAggregateWithNode $createNodeAggregateWithNode, ContentRepository $contentRepository): CreateNodeAggregateWithNode
    {
        $nodeType = $contentRepository->getNodeTypeManager()->getNodeType($createNodeAggregateWithNode->nodeTypeName);
        if (!isset($nodeType->getFullConfiguration()['childNodes'])) {
            return $createNodeAggregateWithNode;
        }
        $nodeAggregateIdsByNodePaths = NodeAggregateIdsByNodePaths::createEmpty();
        foreach (array_keys($nodeType->getFullConfiguration()['childNodes']) as $autoCreatedNodeName) {
            $nodeAggregateIdsByNodePaths = $nodeAggregateIdsByNodePaths->add(
                NodePath::fromString($autoCreatedNodeName),
                NodeAggregateId::create()
            );
        }
        return $createNodeAggregateWithNode->withTetheredDescendantNodeAggregateIds(
            $nodeAggregateIdsByNodePaths->merge($createNodeAggregateWithNode->tetheredDescendantNodeAggregateIds)
        );
    }
}
