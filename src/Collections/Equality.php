<?php declare(strict_types=1);

namespace Google\Generator\Collections;

interface Equality
{
    public function hash() : int;
    public function equals($other) : bool;
}
