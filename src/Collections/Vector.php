<?php declare(strict_types=1);

namespace Google\Generator\Collections;

class Vector implements \IteratorAggregate, \Countable, \ArrayAccess, Equality
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

    public static function Zip(Vector $a, Vector $b, ?Callable $fnMap = null) : Vector
    {
        $count = min(count($a), count($b));
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = [$a[$i], $b[$i]];
        }
        $v = new Vector($result);
        if ($fnMap) {
            $v = $v->map(fn($x) => $fnMap($x[0], $x[1]));
        }
        return $v;
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

    // Equality methods

    public function getHash() : int
    {
        $hash = 1;
        foreach ($this->data as $item) {
            $hash *= 17;
            $hash ^= static::Hash($item);
        }
        return $hash;
    }

    public function isEqualTo($other): bool
    {
        if (!($other instanceof Vector)) {
            return false;
        }
        if (count($this) !== count($other)) {
            return false;
        }
        foreach ($this->data as $key => $item) {
            if (!static::Equal($other->data[$key], $item)) {
                return false;
            }
        }
        return true;
    }

    // Normal class methods

    public function prepend($item): Vector
    {
        $data = $this->data;
        array_unshift($data, $item);
        return new Vector($data);
    }

    public function append($item): Vector
    {
        $data = $this->data;
        $data[] = $item;
        return new Vector($data);
    }

    public function concat(Vector $vector): Vector
    {
        return new Vector(array_merge($this->data, $vector->data));
    }

    public function filter(Callable $fnPredicate): Vector
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

    public function flatten() : Vector
    {
        return $this->flatMap(fn($x) => $x instanceof Vector ? $x->flatten() : Vector::New([$x]));
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

    public function take(int $n): Vector
    {
        return $n >= count($this->data) ? $this : new Vector(array_slice($this->data, 0, $n));
    }

    public function takeLast(int $n): Vector
    {
        return $n >= count($this->data) ? $this : new Vector(array_slice($this->data, count($this->data) - $n));
    }

    public function skip(int $n): Vector
    {
        return $n === 0 ? $this : new Vector(array_slice($this->data, $n));
    }

    public function skipLast(int $n) : Vector
    {
        return new Vector(array_slice($this->data, 0, max(0, count($this->data) - $n)));
    }

    public function skipWhile(Callable $fnPredicate): Vector
    {
        for ($i = 0; $i < count($this->data); $i++) {
            if (!$fnPredicate($this->data[$i])) {
                return $this->skip($i);
            }
        }
        return new Vector([]);
    }

    public function skipLastWhile(Callable $fnPredicate): Vector
    {
        for ($i = count($this->data); $i > 0; $i--) {
            if (!$fnPredicate($this->data[$i - 1])) {
                return $this->take($i);
            }
        }
        return new Vector([]);
    }

    public function firstOrNull()
    {
        return count($this->data) === 0 ? null : $this->data[0];
    }

    public function last()
    {
        return $this->data[count($this->data) - 1];
    }

    public function any($fnPredicate = null) : bool
    {
        foreach ($this->data as $item) {
            if (!$fnPredicate || $fnPredicate($item)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    public function join(string $joiner = '') : string
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

    public function max($defaultValue = null)
    {
        return count($this->data) === 0 ? $defaultValue : max($this->data);
    }

    public function __toString(): string
    {
        if (count($this->data) < 20) {
            $s = $this->join(', ');
        } else {
            $s = $this->take(10)->join(', ') . ' ... ' . $this->takeLast(10)->join(', ');
        }
        return "[{$s}]";
    }
}
