<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\ChildNodes;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class ChildNodesTest extends AbstractNodeTemplateTestCase
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
    public function itMatchesSnapshot2(): void
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
    public function itMatchesSnapshot3(): void
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

    /** @test */
    public function itMatchesSnapshot4(): void
    {
        $this->markTestSkipped('Until https://github.com/neos/neos-development-collection/issues/4351');

        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.AllowedChildNodes',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('AllowedChildNodes');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('AllowedChildNodes', $createdNode);
    }

    /** @test */
    public function itMatchesSnapshot5(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.DisallowedChildNodes',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('DisallowedChildNodes');

        $this->assertCaughtExceptionsMatchesSnapshot('DisallowedChildNodes');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('DisallowedChildNodes', $createdNode);
    }
}
