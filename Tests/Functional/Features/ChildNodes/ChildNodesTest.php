<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\ChildNodes;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTest;

class ChildNodesTest extends AbstractNodeTemplateTest
{
    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.StaticChildNodes',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('ChildNodes');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('ChildNodes', $createdNode);
    }


    /** @test */
    public function testNodeCreationMatchesSnapshot2(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.DynamicChildNodes1',
            [
                'text' => '<p>bar</p>'
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('ChildNodes');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('ChildNodes', $createdNode);
    }

    /** @test */
    public function testNodeCreationMatchesSnapshot3(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.DynamicChildNodes2',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('ChildNodes');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('ChildNodes', $createdNode);
    }
}
