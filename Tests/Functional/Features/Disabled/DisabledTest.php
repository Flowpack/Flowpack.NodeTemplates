<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Disabled;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class DisabledTest extends AbstractNodeTemplateTestCase
{
    protected static bool $showDisabledNodesInSubgraph = true;

    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.Disabled',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('Disabled');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('Disabled', $createdNode);
    }
}
