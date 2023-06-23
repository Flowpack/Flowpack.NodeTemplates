<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Functional\Features\ResolvableProperties;

use Flowpack\NodeTemplates\Tests\Functional\AbstractNodeTemplateTest;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Media\Domain\Repository\ImageRepository;
use Neos\Utility\ObjectAccess;

class ResolvablePropertiesTest extends AbstractNodeTemplateTest
{
    /** @test */
    public function itMatchesSnapshot1(): void
    {
        $this->createFakeNode('some-node-id');
        $this->createFakeNode('other-node-id');

        $resource = $this->objectManager->get(ResourceManager::class)->importResource(__DIR__ . '/image.png');

        $asset = new Asset($resource);
        ObjectAccess::setProperty($asset, 'Persistence_Object_Identifier', 'c228200e-7472-4290-9936-4454a5b5692a', true);
        $this->objectManager->get(AssetRepository::class)->add($asset);

        $resource2 = $this->objectManager->get(ResourceManager::class)->importResource(__DIR__ . '/image.png');

        $image = new Image($resource2);
        ObjectAccess::setProperty($image, 'Persistence_Object_Identifier', 'c8ae9f9f-dd11-4373-bf42-4bf31ec5bd19', true);
        $this->objectManager->get(ImageRepository::class)->add($image);

        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.ResolvableProperties',
            [
                'realNode' => $this->createFakeNode('real-node-id')
            ]
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('ResolvableProperties');
        $this->assertNoExceptionsWereCaught();
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('ResolvableProperties', $createdNode);
    }

    /** @test */
    public function itMatchesSnapshot2(): void
    {
        $createdNode = $this->createNodeInto(
            $this->homePageMainContentCollectionNode,
            'Flowpack.NodeTemplates:Content.UnresolvableProperties',
            []
        );

        $this->assertLastCreatedTemplateMatchesSnapshot('UnresolvableProperties');

        $this->assertCaughtExceptionsMatchesSnapshot('UnresolvableProperties');
        $this->assertNodeDumpAndTemplateDumpMatchSnapshot('UnresolvableProperties', $createdNode);
    }
}
