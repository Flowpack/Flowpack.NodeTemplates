<?php
declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Ui\NodeCreationHandler\DocumentTitleNodeCreationHandler;
use Neos\Neos\Ui\NodeCreationHandler\NodeCreationHandlerInterface;

/**
 * Augments the original neos ui document title node creation handler, which takes care of setting the "uriPathSegment" based of the title.
 * This handler steps in when a node has a node template with the property `uriPathSegment`.
 * In this case we will prevent the original handler from being called, as we handle setting the `uriPathSegment` ourselves and the original handler will just override our `uriPathSegment` again.
 *
 * @todo once we have sorting with https://github.com/neos/neos-ui/pull/3511 we can put our handler at the end instead.
 */
class DelegatingDocumentTitleNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @Flow\Inject
     * @var DocumentTitleNodeCreationHandler
     */
    protected $originalDocumentTitleNodeCreationHandler;

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
            $this->originalDocumentTitleNodeCreationHandler->handle($node, $data);
            return;
        }

        // do nothing, as we handle this already when applying the template
    }
}
