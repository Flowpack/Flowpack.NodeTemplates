<?php
namespace Flowpack\NodeTemplates\Tests\Unit\Domain\NodeCreation;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\NodeTemplates\Domain\NodeCreation\PropertyType;
use Flowpack\NodeTemplates\Tests\Unit\Domain\NodeCreation\Fixture\PostalAddress;
use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Media\Domain\Model\Asset;
use Neos\Media\Domain\Model\Image;
use Neos\Media\Domain\Model\ImageInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

require_once(__DIR__ . '/Fixture/PostalAddress.php');

/**
 * Test cases for the PropertyType value object
 *
 * copied and adjusted from
 * https://github.com/neos/neos-development-collection/blob/cc02be6c06a55110e9d7a820820513c51c68882c/Neos.ContentRepository.Core/Tests/Unit/Infrastructure/Property/PropertyTypeTest.php#L32
 */
class PropertyTypeTest extends TestCase
{
    /**
     * @dataProvider declarationAndValueProvider
     */
    public function testIsMatchedBy(array $declarationsByType, array $validValues, array $invalidValues): void
    {
        foreach ($declarationsByType as $declaration) {
            $nodeTypeMock = $this->getMockBuilder(NodeType::class)->disableOriginalConstructor()->getMock();
            $nodeTypeMock->expects(self::once())->method('getPropertyType')->with('test')->willReturn($declaration);
            $subject = PropertyType::fromPropertyOfNodeType(
                'test',
                $nodeTypeMock,
            );
            foreach ($validValues as $validValue) {
                Assert::assertTrue($subject->isMatchedBy($validValue));
            }
            foreach ($invalidValues as $invalidValue) {
                Assert::assertFalse($subject->isMatchedBy($invalidValue));
            }
        }
    }

    public function declarationAndValueProvider(): array
    {
        $bool = true;
        $int = 42;
        $float = 4.2;
        $string = 'It\'s a graph!';
        $array = [$string];
        $image = new Image(new PersistentResource());
        $asset = new Asset(new PersistentResource());
        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::W3C, '2020-08-20T18:56:15+00:00');
        $uri = new Uri('https://www.neos.io');
        $postalAddress = PostalAddress::dummy();

        return [
            [
                ['bool', 'boolean'],
                [$bool, null],
                [0, $int, 0.0, $float, '', $string, [], $array, $date, $uri, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                ['int', 'integer'],
                [42, null],
                [$bool, $float, $string, $array, $date, $uri, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                ['float', 'double'],
                [4.2, null],
                [$bool, $int, $string, $array, $date, $uri, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                ['string'],
                ['', null],
                [$bool, $int, $float, $array, $date, $uri, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                ['array'],
                [[], $array, [$asset], null],
                [$bool, $int, $float, $string, $date, $uri, $postalAddress, $image, $asset]
            ],
            [
                [\DateTime::class, \DateTimeImmutable::class, \DateTimeInterface::class],
                [$date, null],
                [$bool, $int, $float, $string, $array, $uri, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                ['Uri', Uri::class, UriInterface::class],
                [$uri, null],
                [$bool, $int, $float, $string, $array, $date, $postalAddress, $image, $asset, [$asset]]
            ],
            [
                [PostalAddress::class],
                [$postalAddress, null],
                [$bool, $int, $float, $string, $array, $date, $uri, $image, $asset, [$asset]]
            ],
            [
                [ImageInterface::class],
                [$image, null],
                [$bool, $int, $float, $string, $array, $date, $uri, $postalAddress, $asset, [$image]]
            ],
            [
                [Asset::class],
                [$asset, $image, null],
                [$bool, $int, $float, $string, $array, $date, $uri, $postalAddress, [$asset]]
            ],
            [
                ['array<' . Asset::class . '>'],
                [[$asset], [$image], null],
                [$bool, $int, $float, $string, $array, $date, $uri, $postalAddress, $image, $asset]
            ],
            [
                ['array<string>'],
                [[], [$string], [$string, ''], null],
                [$bool, $int, $float, $string, [$string, $int], $date, $uri, $postalAddress, $image, $asset, [$bool], [$float]]
            ],
            [
                ['array<integer>'],
                [[], [$int], [$int, 23432], null],
                [$bool, $int, $float, $string, $date, $uri, $postalAddress, $image, $asset, [$bool], [$float]]
            ],
        ];
    }

    /**
     * @dataProvider declarationTypeProvider
     * @param array $declaredTypes
     * @param string $expectedSerializationType
     */
    public function testGetValue(array $declaredTypes, string $expectedSerializationType): void
    {
        foreach ($declaredTypes as $declaredType) {
            $nodeTypeMock = $this->getMockBuilder(NodeType::class)->disableOriginalConstructor()->getMock();
            $nodeTypeMock->expects(self::once())->method('getPropertyType')->with('test')->willReturn($declaredType);
            $subject = PropertyType::fromPropertyOfNodeType(
                'test',
                $nodeTypeMock,
            );
            $actualSerializationType = $subject->getValue();
            Assert::assertSame(
                $expectedSerializationType,
                $actualSerializationType,
                'Serialization type does not match for declared type "' . $declaredType . '". Expected "' . $expectedSerializationType . '", got "' . $actualSerializationType . '"'
            );
        }
    }

    public function declarationTypeProvider(): array
    {
        return [
            [['bool', 'boolean'], 'boolean'],
            [['int', 'integer'], 'integer'],
            [['float', 'double'], 'float'],
            [['string', ], 'string'],
            [['array', ], 'array'],
            [['DateTime', 'DateTimeImmutable', 'DateTimeInterface'], 'DateTimeImmutable'],
            [['Uri', Uri::class, UriInterface::class], Uri::class],
            [[PostalAddress::class], PostalAddress::class],
            [[ImageInterface::class], ImageInterface::class],
            [[Asset::class], Asset::class],
            [['array<' . Asset::class . '>'], 'array<' . Asset::class . '>'],
        ];
    }
}
