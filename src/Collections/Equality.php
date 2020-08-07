<?php declare(strict_types=1);

namespace Google\Generator\Collections;

interface Equality
{
    public function getHash() : int;
    public function isEqualTo($other) : bool;
}
