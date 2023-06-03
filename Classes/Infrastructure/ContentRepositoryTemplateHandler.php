<?php

namespace Flowpack\NodeTemplates\Infrastructure;

use Flowpack\NodeTemplates\Domain\RootTemplate;
use Flowpack\NodeTemplates\Domain\Templates;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Service\NodeOperations;

class ContentRepositoryTemplateHandler
{
    /**
     * @var NodeOperations
     * @Flow\Inject
     */
    protected $nodeOperations;

    /**
     * Applies the root template and its descending configured child node templates on the given node.
     */
    public function apply(RootTemplate $template, NodeInterface $node): void
    {
        foreach ($template->getProperties() as $key => $value) {
            $node->setProperty($key, $value);
        }
        $this->applyTemplateRecursively($template->getChildNodes(), $node);
    }

    private function applyTemplateRecursively(Templates $templates, NodeInterface $parentNode): void
    {
        foreach ($templates as $template) {
            if ($template->getName() && $parentNode->getNodeType()->hasAutoCreatedChildNode($template->getName())) {
                $node = $parentNode->getNode($template->getName()->__toString());
                foreach ($template->getProperties() as $key => $value) {
                    $node->setProperty($key, $value);
                }
            } else {
                $node = $this->nodeOperations->create(
                    $parentNode,
                    [
                        'nodeType' => $template->getType()->getValue(),
                        'nodeName' => $template->getName() ? $template->getName()->__toString() : null
                    ],
                    'into'
                );
                foreach ($template->getProperties() as $key => $value) {
                    $node->setProperty($key, $value);
                }
            }
            $this->applyTemplateRecursively($template->getChildNodes(), $node);
        }
    }
}
