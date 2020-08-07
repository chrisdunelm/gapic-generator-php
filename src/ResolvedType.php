<?php declare(strict_types=1);

namespace Google\Generator;

class ResolvedType
{
    public string $typeName; // readonly

    public function __construct($typeName)
    {
        $this->typeName = $typeName;
    }

    public function __toString()
    {
        return $this->typeName;
    }
}
