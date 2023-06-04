<?php

namespace Flowpack\NodeTemplates\Infrastructure;

use Flowpack\NodeTemplates\Domain\CaughtException;
use Flowpack\NodeTemplates\Domain\CaughtExceptions;
use Flowpack\NodeTemplates\Domain\RootTemplate;
use Flowpack\NodeTemplates\Domain\Template;
use Flowpack\NodeTemplates\Domain\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Exception\NodeConstraintException;
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
     * @throws \InvalidArgumentException
     */
    public function apply(RootTemplate $template, NodeInterface $node, CaughtExceptions $caughtExceptions): void
    {
        $validProperties = $this->requireValidProperties($template->getProperties(), $node->getNodeType(), $caughtExceptions);
        foreach ($validProperties as $key => $value) {
            $node->setProperty($key, $value);
        }
        $this->ensureNodeHasUriPathSegment($node, $template);
        $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
    }

    private function applyTemplateRecursively(Templates $templates, NodeInterface $parentNode, CaughtExceptions $caughtExceptions): void
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->getNodeType()->hasAutoCreatedChildNode($template->getName())) {
                $node = $parentNode->getNode($template->getName()->__toString());
                assert($template->getType() === null);
            } else {
                assert($template->getType() !== null);
                try {
                    $node = $this->nodeOperations->create(
                        $parentNode,
                        [
                            'nodeType' => $template->getType()->getValue(),
                            'nodeName' => $template->getName() ? $template->getName()->__toString() : null
                        ],
                        'into'
                    );
                } catch (NodeConstraintException $nodeConstraintException) {
                    $caughtExceptions->add(
                        CaughtException::fromException($nodeConstraintException)
                    );
                    continue; // try the next childNode
                }
            }
            $validProperties = $this->requireValidProperties($template->getProperties(), $node->getNodeType(), $caughtExceptions);
            foreach ($validProperties as $key => $value) {
                $node->setProperty($key, $value);
            }
            $this->ensureNodeHasUriPathSegment($node, $template);
            $this->applyTemplateRecursively($template->getChildNodes(), $node, $caughtExceptions);
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
    private function assertValidPropertyName($propertyName): void
    {
        $legacyInternalProperties = ['_accessRoles', '_contentObject', '_hidden', '_hiddenAfterDateTime', '_hiddenBeforeDateTime', '_hiddenInIndex',
            '_index', '_name', '_nodeType', '_removed', '_workspace'];
        if (!is_string($propertyName) || $propertyName === '') {
            throw new \InvalidArgumentException(sprintf('Property name must be a non empty string. Got "%s".', $propertyName));
        }
        if ($propertyName[0] === '_') {
            $lowerPropertyName = strtolower($propertyName);
            foreach ($legacyInternalProperties as $legacyInternalProperty) {
                if ($lowerPropertyName === strtolower($legacyInternalProperty)) {
                    throw new \InvalidArgumentException(sprintf('Internal legacy properties are not implement. Got "%s".', $propertyName));
                }
            }
        }
    }

    /**
     * A few checks are run against the properties before they are applied on the node.
     *
     * 1. It is checked, that only properties will be set, that were declared in the NodeType
     *
     * 2. In case the property is a select-box, it is checked, that the current value is a valid option of the select-box
     *
     * 3. It is made sure is that a property value is not null when there is a default value:
     *  In case that due to a condition in the nodeTemplate `null` is assigned to a node property, it will override the defaultValue.
     *  This is a problem, as setting `null` might not be possible via the Neos UI and the Fusion rendering is most likely not going to handle this edge case.
     *  Related discussion {@link https://github.com/Flowpack/Flowpack.NodeTemplates/issues/41}
     */
    private function requireValidProperties(array $properties, NodeType $nodeType, CaughtExceptions $caughtExceptions): array
    {
        $validProperties = [];
        $defaultValues = $nodeType->getDefaultValuesForProperties();
        foreach ($properties as $propertyName => $propertyValue) {
            $this->assertValidPropertyName($propertyName);
            try {
                if (!isset($nodeType->getProperties()[$propertyName])) {
                    $value = json_encode($propertyValue);
                    throw new PropertyNotSetException(
                        sprintf(
                            'Because property is not declared in NodeType. Got value "%s".',
                            $value
                        ),
                        1685869035209
                    );
                }
                if (array_key_exists($propertyName, $defaultValues) && $propertyValue === null) {
                    throw new PropertyNotSetException(
                        sprintf(
                            'Because property is "null" and would override the default value "%s".',
                            json_encode($defaultValues[$propertyName])
                        ),
                        1685869035371
                    );
                }
                $propertyConfiguration = $nodeType->getProperties()[$propertyName];
                $editor = $propertyConfiguration['ui']['inspector']['editor'] ?? null;
                $type = $propertyConfiguration['type'] ?? null;
                $selectBoxValues = $propertyConfiguration['ui']['inspector']['editorOptions']['values'] ?? null;
                if ($editor === 'Neos.Neos/Inspector/Editors/SelectBoxEditor' && $selectBoxValues && in_array($type, ['string', 'array'], true)) {
                    $selectedValue = $type === 'string' ? [$propertyValue] : $propertyValue;
                    $difference = array_diff($selectedValue, array_keys($selectBoxValues));
                    if (\count($difference) !== 0) {
                        throw new PropertyNotSetException(
                            sprintf(
                                'Because property has illegal select-box value(s): (%s)',
                                join(', ', $difference)
                            ),
                            1685869035452
                        );
                    }
                }
                $validProperties[$propertyName] = $propertyValue;
            } catch (PropertyNotSetException $propertyNotSetException) {
                $caughtExceptions->add(
                    CaughtException::fromException($propertyNotSetException)->withOrigin(sprintf('Property "%s" in NodeType "%s"', $propertyName, $nodeType->getName()))
                );
            }
        }
        return $validProperties;
    }
}
