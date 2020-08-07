<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Protobuf\Internal\GPBType;
use \Google\Protobuf\Internal\Descriptor;
use \Google\Generator\Collections\Vector;

class Type
{
    public static function bool(): Type
    {
        return new Type(null, 'bool');
    }

    public static function int(): Type
    {
        return new Type(null, 'int');
    }

    public static function string(): Type
    {
        return new Type(null, 'string');
    }

    public static function array(): Type
    {
        return new Type(null, 'array');
    }

    public static function FromName(string $fullname): Type
    {
        $parts = Vector::New(explode('\\', $fullname));
        return new Type($parts->skipLast(1), $parts->last());
    }

    public static function FromMessage(Descriptor $desc): Type
    {
        $fullname = $desc->getClass();
        return static::FromName($fullname);
    }

    public static function FromField(FieldDetails $field): Type
    {
        switch ($field->type) {
            case GPBType::BOOL: return static::bool();
            case GPBType::INT32: return static::int();
            case GPBType::STRING: return static::string();
            default: throw new \Exception('Cannot convert that field type fo a Type');
        }
    }

    private function __construct(?Vector $namespaceParts, string $name)
    {
        $this->namespaceParts = $namespaceParts;
        $this->name = $name;
    }

    public ?Vector $namespaceParts; // readonly
    public string $name; // readonly

    public function isClass(): bool
    {
        return !is_null($this->namespaceParts);
    }

    public function getNamespace(): string
    {
        return is_null($this->namespaceParts) ? '' : $this->namespaceParts->join('\\');
    }

    public function getFullname(): string
    {
        if (is_null($this->namespaceParts)) {
            return $this->name;
        } else {
            return "\\{$this->namespaceParts->map(fn($x) => "{$x}\\")->join()}{$this->name}";
        }
    }
}
