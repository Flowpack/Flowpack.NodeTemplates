<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\NodeNames;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTest;

class NodeNamesTest extends AbstractNodeTemplateTest
{
    /** @test */
    public function itMatchesSnapshot(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.NodeNames',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('NodeNames');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('NodeNames', $createdNode);
    }
}
