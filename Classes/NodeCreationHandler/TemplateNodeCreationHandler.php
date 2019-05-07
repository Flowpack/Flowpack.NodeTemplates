<?php

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Neos\Flow\Annotations as Flow;
use Flowpack\NodeTemplates\Template;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Property\PropertyMapper;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;


class TemplateNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @var PropertyMapper
     * @Flow\Inject
     */
    protected $propertyMapper;

    /**
     * @var integer
     * @Flow\InjectConfiguration(path="nodeCreationDepth")
     */
    protected $nodeCreationDepth;

    /**
     * Create child nodes and change properties upon node creation
     *
     * @param NodeInterface $node The newly created node
     * @param array $data incoming data from the creationDialog
     * @return void
     */
    public function handle(NodeInterface $node, array $data)
    {
        if ($node->getNodeType()->hasConfiguration('options.template')) {
            $templateConfiguration = $node->getNodeType()->getConfiguration('options.template');
        } else {
            return;
        }

        $propertyMappingConfiguration = $this->propertyMapper->buildPropertyMappingConfiguration();

        $subPropertyMappingConfiguration = $propertyMappingConfiguration;
        for ($i = 0; $i < $this->nodeCreationDepth; $i++) {
            $subPropertyMappingConfiguration = $subPropertyMappingConfiguration
                ->forProperty('childNodes.*')
                ->allowAllProperties();
        }

        /** @var Template $template */
        $template = $this->propertyMapper->convert($templateConfiguration, Template::class,
            $propertyMappingConfiguration);

        $context = [
            'data' => $data,
            'triggeringNode' => $node,
        ];
        $template->apply($node, $context);
        return;
    }
}
