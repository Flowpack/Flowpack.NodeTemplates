<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\NodeNames;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class NodeNamesTest extends AbstractNodeTemplateTestCase
{
    /** @test */
    public function itMatchesSnapshot1(): void
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

    /** @test */
    public function itMatchesSnapshot2(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.NestedNodeNames',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('NestedNodeNames');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('NestedNodeNames', $createdNode);
    }
}
