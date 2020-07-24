<?php declare(strict_types=1);

namespace Google\Generator\Collections;

class Set implements \IteratorAggregate, \Countable, \ArrayAccess
{
    public static function New($data = []) : Set
    {
        if ($data instanceof Set) {
            return $data;
        }
        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }
        if (is_array($data)) {
            $pairs = [];
            foreach ($data as $v) {
                $pairs[] = [$v, TRUE];
            }
            return new Set(Map::FromPairs($pairs));
        }
        throw new \Exception('Set::New accepts a Traversable or an array only');
    }

    private $map;

    private function __construct(Map $map)
    {
        $this->map = $map;
    }

    // IteratorAggregate methods

    public function getIterator()
    {
        return (function() {
            foreach ($this->map as [$k]) {
                yield $k;
            }
        })();
    }

    // Countable methods

    public function count() : int
    {
        return count($this->map);
    }

    // ArrayAccess methods

    public function offsetExists($key) : bool
    {
        return isset($this->map[$key]);
    }

    public function offsetGet($key) : bool
    {
        return isset($this->map[$key]);
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Set is readonly');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Set is readonly');
    }

    // Normal class methods

    public function add($key) : Set
    {
        if (isset($this->map[$key])) {
            return $this;
        } else {
            $map = $this->map->set($key, TRUE);
            return new Set($map);
        }
    }
}
