<?php declare(strict_types=1);

namespace Google\Generator\Collections;

class Vector implements \IteratorAggregate, \Countable, \ArrayAccess
{
    use EqualityHelper;

    public static function New($data = []) : Vector
    {
        if ($data instanceof Vector) {
            return $data;
        }
        if ($data instanceof \Traversable) {
            return new Vector(iterator_to_array($data));
        }
        if (is_array($data)) {
            return new Vector(array_values($data));
        }
        throw new \Exception('Vector::New accepts a Traversable or an array only');
    }

    public static function Zip(Vector $a, Vector $b) : Vector
    {
        $count = min(count($a), count($b));
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = [$a[$i], $b[$i]];
        }
        return new Vector($result);
    }

    private $data;

    private function __construct($data)
    {
        $this->data = $data;
    }

    // IteratorAggregate methods

    public function getIterator()
    {
        return (function() {
            foreach ($this->data as $k => $v) {
                yield $k => $v;
            }
        })();
    }

    // Countable methods

    public function count() : int
    {
        return count($this->data);
    }

    // ArrayAccess methods

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Vector is readonly');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Vector is readonly');
    }

    // Normal class methods

    public function append($item) : Vector
    {
        $data = $this->data;
        $data[] = $item;
        return new Vector($data);
    }

    public function concat(Vector $vector) : Vector
    {
        return new Vector($this->data + $vector->data);
    }

    public function filter(Callable $fnPredicate) : Vector
    {
        $result = [];
        foreach ($this->data as $item) {
            if ($fnPredicate($item)) {
                $result[] = $item;
            }
        }
        return new Vector($result);
    }

    public function map(Callable $fnMap) : Vector
    {
        $result = [];
        foreach ($this->data as $item) {
            $result[] = $fnMap($item);
        }
        return new Vector($result);
    }

    public function flatMap(Callable $fnFlatMap) : Vector
    {
        $parts = [];
        foreach ($this->data as $item) {
            $mapping = $fnFlatMap($item);
            if (!($mapping instanceof Vector)) {
                throw new \Exception("flatMap() function must return a Vector");
            }
            $parts[] = $mapping->data;
        }
        return new Vector(array_merge(...$parts));
    }

    public function groupBy(Callable $fnKey, Callable $fnValue = NULL) : Map
    {
        $map = Map::New();
        foreach ($this->data as $item) {
            $key = $fnKey($item);
            $value = $fnValue ? $fnValue($item) : $item;
            $mapValue = isset($map[$key]) ? $map[$key]->append($value) : Vector::New([$value]);
            $map = $map->set($key, $mapValue);
        }
        return $map;
    }

    public function distinct() : Vector
    {
        $set = Set::New();
        $data = [];
        foreach ($this->data as $item) {
            if (!$set[$item]) {
                $set = $set->add($item);
                $data[] = $item;
            }
        }
        return new Vector($data);
    }

    public function any($fnPredicate) : bool
    {
        foreach ($this->data as $item) {
            if ($fnPredicate($item)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function join(string $joiner) : string
    {
        return implode($joiner, $this->data);
    }

    public function contains($item) : bool
    {
        foreach ($this->data as $dataItem) {
            if (static::Equal($item, $dataItem)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function toMap(Callable $fnKey, Callable $fnValue = NULL) : Map
    {
        $pairs = [];
        foreach ($this->data as $item) {
            $pairs[] = [$fnKey($item), $fnValue ? $fnValue($item) : $item];
        }
        return Map::FromPairs($pairs);
    }

    public function toSet() : Set
    {
        return Set::New($this);
    }

    public function toArray() : array
    {
        return $this->data;
    }
}
