<?php declare(strict_types=1);

namespace Google\Generator\Utils;

class Helpers
{
    public static function ToSnakeCase(string $s)
    {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $s));
    }

    public static function ToCamelCase(string $s)
    {
        // TODO: This is mostly broken
        return strtolower($s[0]) . substr($s, 1);
    }
}
