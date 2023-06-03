<?php

namespace Flowpack\NodeTemplates\Infrastructure;

use Flowpack\NodeTemplates\Domain\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template;
use Flowpack\NodeTemplates\Domain\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\NodeOperations;
use Neos\Neos\Utility\NodeUriPathSegmentGenerator;

class ContentRepositoryTemplateHandler
{
    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     */
    public function apply(RootTemplate $template, NodeInterface $node): void
    {
        $this->assertPropertiesAreValid($template->getProperties());
        foreach ($template->getProperties() as $key => $value) {
            $node->setProperty($key, $value);
        }
        $this->ensureNodeHasUriPathSegment($node, $template);
        $this->applyTemplateRecursively($template->getChildNodes(), $node);
    }

    private function applyTemplateRecursively(Templates $templates, NodeInterface $parentNode): void
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->getNodeType()->hasAutoCreatedChildNode($template->getName())) {
                $node = $parentNode->getNode($template->getName()->__toString());
            } else {
                $node = $this->nodeOperations->create(
                    $parentNode,
                    [
                        'nodeType' => $template->getType()->getValue(),
                        'nodeName' => $template->getName() ? $template->getName()->__toString() : null
                    ],
                    'into'
                );
            }
            $this->assertPropertiesAreValid($template->getProperties());
            foreach ($template->getProperties() as $key => $value) {
                $node->setProperty($key, $value);
            }
            $this->ensureNodeHasUriPathSegment($node, $template);
            $this->applyTemplateRecursively($template->getChildNodes(), $node);
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

    /**
     * In the old CR, it was common practice to set internal or meta properties via this syntax: `_hidden` but we don't allow this anymore.
     * @throws \InvalidArgumentException
     */
    private function assertPropertiesAreValid(array $properties): array
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
}
