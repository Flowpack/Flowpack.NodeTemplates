<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Tests\Unit\Domain\NodeCreation;

use Flowpack\NodeTemplates\Domain\NodeCreation\InvalidReferenceException;
use Flowpack\NodeTemplates\Domain\NodeCreation\ReferenceType;
use Flowpack\NodeTemplates\Tests\Unit\NodeMockTrait;
use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use PHPUnit\Framework\TestCase;

class ReferenceTypeTest extends TestCase
{
    use NodeMockTrait;

    private const VALID_NODE_ID_1 = '123';
    private const VALID_NODE_ID_2 = '456';

    /**
     * @dataProvider declarationAndValueProvider
     */
    public function testIsMatchedBy(string $declarationType, array $validValues, array $invalidValues): void
    {
        $nodeTypeMock = $this->getMockBuilder(NodeType::class)->disableOriginalConstructor()->getMock();
        $nodeTypeMock->expects(self::once())->method('getPropertyType')->with('test')->willReturn($declarationType);
        $subject = ReferenceType::fromPropertyOfNodeType(
            'test',
            $nodeTypeMock,
        );
        foreach ($validValues as $validValue) {
            $subject->isReference() ? $subject->toNodeAggregateId($validValue) : $subject->toNodeAggregateIds($validValue);
            self::assertTrue(true);
        }
        foreach ($invalidValues as $invalidValue) {
            try {
                $subject->isReference() ? $subject->toNodeAggregateId($invalidValue) : $subject->toNodeAggregateIds($invalidValue);
                self::fail(sprintf('Value %s should not match.', var_export($invalidValue, true)));
            } catch (InvalidReferenceException $exception) {
                self::assertTrue(true);
            }
        }
    }

    public function declarationAndValueProvider(): array
    {
        $int = 13;
        $float = 4.2;
        $stringWithSpecialChars = 'Special äüö chars';
        $stringWithSpecialCharsArray = [$stringWithSpecialChars];
        $image = new Image(new PersistentResource());
        $asset = new Asset(new PersistentResource());
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::W3C, '2020-08-20T18:56:15+00:00');
        $uri = new Uri('https://www.neos.io');

        $nodeMock1 = $this->createNodeMock(NodeAggregateId::fromString(self::VALID_NODE_ID_1));
        $nodeMock2 = $this->createNodeMock(NodeAggregateId::fromString(self::VALID_NODE_ID_2));

        return [
            [
                'reference',
                [null, $nodeMock1, $nodeMock2, self::VALID_NODE_ID_1, self::VALID_NODE_ID_2],
                [0, $int, 0.0, $float, '', $stringWithSpecialChars, [], $stringWithSpecialCharsArray, $date, $uri, $image, $asset, [$asset]]
            ],
            [
                'references',
                [[], null, [self::VALID_NODE_ID_1], [$nodeMock1], [self::VALID_NODE_ID_2, $nodeMock2]],
                [true, $float, $stringWithSpecialChars, $stringWithSpecialCharsArray, $date, $uri, $image, $asset, [$asset]]
            ],
        ];
    }
}
