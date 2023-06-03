<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\NodeCreationHandler;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\DocumentTitleNodeCreationHandler;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

class TemplatingDocumentTitleNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @Flow\Inject
     * @var DocumentTitleNodeCreationHandler
     */
    protected $augmentedDocumentTitleNodeCreationHandler;

    /**
     * @throws \Neos\Eel\Exception
     * @throws \Neos\Neos\Exception
     */
    public function handle(NodeInterface $node, array $data): void
    {
        $template = $node->getNodeType()->getOptions()['template'] ?? null;
        if (
            !$template
            || !isset($template['properties']['uriPathSegment'])
        ) {
            $this->augmentedDocumentTitleNodeCreationHandler->handle($node, $data);
            return;
        }

        // do nothing, as we handle this already when applying the template
    }
}
