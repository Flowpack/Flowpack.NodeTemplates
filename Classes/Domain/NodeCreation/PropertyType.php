<?php

declare(strict_types=1);

namespace Flowpack\NodeTemplates\Domain\NodeCreation;

use GuzzleHttp\Psr7\Uri;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\UriInterface;

/**
 * The property type value object as declared in a NodeType
 *
 * Implementation copied and adjusted (experimental nullable handling was removed) from {@see \Neos\ContentRepository\Core\Infrastructure\Property\PropertyType}
 * {@link https://github.com/neos/neos-development-collection/blob/31cb00aa0bb513c9e8878807a0de23772f50d992/Neos.ContentRepository.Core/Classes/Infrastructure/Property/PropertyType.php#L30}
 *
 * @Flow\Proxy(false)
 */
final class PropertyType
{
    public const TYPE_BOOL = 'boolean';
    public const TYPE_INT = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_STRING = 'string';
    public const TYPE_ARRAY = 'array';
    public const TYPE_DATE = 'DateTimeImmutable';

    public const PATTERN_ARRAY_OF = '/array<[^>]+>/';

    private string $value;

    private ?self $arrayOfType;

    private function __construct(
        string $value
    ) {
        $this->value = $value;
        if ($this->isArrayOf()) {
            $arrayOfType = self::tryFromString($this->getArrayOf());
            if (!$arrayOfType && !$arrayOfType->isArray()) {
                throw new \DomainException(sprintf(
                    'Array declaration "%s" has invalid subType. Expected either class string or int',
                    $this->value
                ));
            }
            $this->arrayOfType = $arrayOfType;
        }
    }

    public static function fromPropertyOfNodeType(
        string $propertyName,
        NodeType $nodeType
    ): self {
        $declaration = $nodeType->getPropertyType($propertyName);
        if ($declaration === 'reference' || $declaration === 'references') {
            throw new \DomainException(
                sprintf(
                    'Given property "%s" is declared as "reference" in node type "%s" and must be treated as such.',
                    $propertyName,
                    $nodeType->getName()
                ),
                1685964835205
            );
        }
        $type = self::tryFromString($declaration);
        if (!$type) {
            throw new \DomainException(
                sprintf(
                    'Given property "%s" is declared as undefined type "%s" in node type "%s"',
                    $propertyName,
                    $declaration,
                    $nodeType->getName()
                ),
                1685952798732
            );
        }
        return $type;
    }

    public static function tryFromString(string $declaration): ?self
    {
        if ($declaration === 'reference' || $declaration === 'references') {
            return null;
        }
        if ($declaration === 'bool' || $declaration === 'boolean') {
            return self::bool();
        }
        if ($declaration === 'int' || $declaration === 'integer') {
            return self::int();
        }
        if ($declaration === 'float' || $declaration === 'double') {
            return self::float();
        }
        if (
            in_array($declaration, [
                'DateTime',
                '\DateTime',
                'DateTimeImmutable',
                '\DateTimeImmutable',
                'DateTimeInterface',
                '\DateTimeInterface'
            ])
        ) {
            return self::date();
        }
        if ($declaration === 'Uri' || $declaration === Uri::class || $declaration === UriInterface::class) {
            $declaration = Uri::class;
        }
        $className = $declaration[0] != '\\'
            ? '\\' . $declaration
            : $declaration;
        if (
            $declaration !== self::TYPE_FLOAT
            && $declaration !== self::TYPE_STRING
            && $declaration !== self::TYPE_ARRAY
            && !class_exists($className)
            && !interface_exists($className)
            && !preg_match(self::PATTERN_ARRAY_OF, $declaration)
        ) {
            return null;
        }
        return new self($declaration);
    }

    public static function bool(): self
    {
        return new self(self::TYPE_BOOL);
    }

    public static function int(): self
    {
        return new self(self::TYPE_INT);
    }

    public static function string(): self
    {
        return new self(self::TYPE_STRING);
    }

    public static function float(): self
    {
        return new self(self::TYPE_FLOAT);
    }

    public static function date(): self
    {
        return new self(self::TYPE_DATE);
    }

    public function isBool(): bool
    {
        return $this->value === self::TYPE_BOOL;
    }

    public function isInt(): bool
    {
        return $this->value === self::TYPE_INT;
    }

    public function isFloat(): bool
    {
        return $this->value === self::TYPE_FLOAT;
    }

    public function isString(): bool
    {
        return $this->value === self::TYPE_STRING;
    }

    public function isArray(): bool
    {
        return $this->value === self::TYPE_ARRAY;
    }

    public function isArrayOf(): bool
    {
        return (bool)preg_match(self::PATTERN_ARRAY_OF, $this->value);
    }

    public function isDate(): bool
    {
        return $this->value === self::TYPE_DATE;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    private function getArrayOf(): string
    {
        return \mb_substr($this->value, 6, -1);
    }

    public function isMatchedBy($propertyValue): bool
    {
        if (is_null($propertyValue)) {
            return true;
        }
        if ($this->isBool()) {
            return is_bool($propertyValue);
        }
        if ($this->isInt()) {
            return is_int($propertyValue);
        }
        if ($this->isFloat()) {
            return is_float($propertyValue);
        }
        if ($this->isString()) {
            return is_string($propertyValue);
        }
        if ($this->isArray()) {
            return is_array($propertyValue);
        }
        if ($this->isDate()) {
            return $propertyValue instanceof \DateTimeInterface;
        }
        if ($this->isArrayOf()) {
            if (!is_array($propertyValue)) {
                return false;
            }
            foreach ($propertyValue as $value) {
                if (!$this->arrayOfType->isMatchedBy($value)) {
                    return false;
                }
            }
            return true;
        }

        $className = $this->value[0] != '\\'
            ? '\\' . $this->value
            : $this->value;

        return (class_exists($className) || interface_exists($className)) && $propertyValue instanceof $className;
    }
}
