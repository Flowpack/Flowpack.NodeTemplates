<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\Properties;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTestCase;

class PropertiesTest extends AbstractNodeTemplateTestCase
{
    /** @test */
    public function itMatchesSnapshot(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.Properties',
            [
                'someNode' => $this->createFakeNode('7f7bac1c-9400-4db5-bbaa-2b8251d127c5')
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('Properties');

        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('Properties', $createdNode);
    }
}
