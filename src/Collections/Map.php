<?php declare(strict_types=1);

namespace Google\Generator\Collections;

class Map implements \IteratorAggregate, \Countable, \ArrayAccess
{
    use EqualityHelper;

    public static function New($data = []) : Map
    {
        if ($data instanceof Map) {
            return $data;
        }
        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }
        if (is_array($data)) {
            $pairs = [];
            foreach ($data as $k => $v) {
                $pairs[] = [$k, $v];
            }
            return static::FromPairs($pairs);
        }
        throw new \Exception('Map::New accepts a Traversable or an array only');
    }

    public static function FromPairs(array $pairs) : Map
    {
        $data = [];
        foreach ($pairs as [$k, $v]) {
            if (static::Apply($data, $k, 1, $v)[0]) {
                throw new \Exception('Cannot add two items with the same key');
            }
        }
        return new Map($data, count($pairs));
    }


    private static function Apply(&$data, $k, $action, $v) : array
    {
        if (is_null($k)) {
            throw new \Exception('NULL keys are invalid');
        }
        $hash = static::Hash($k);
        if (isset($data[$hash])) {
            foreach ($data[$hash] as $index => [$k0, $v0]) {
                if (static::Equal($k0, $k)) {
                    if ($action === 1) {
                        $data[$hash][$index] = [$k, $v];
                    } elseif ($action == -1) {
                        unset($data[$hash][$index]);
                    }
                    return [TRUE, $v0];
                }
            }
            if ($action === 1) {
                $data[$hash][] = [$k, $v];
            }
            return [FALSE, NULL];
        } else {
            if ($action === 1) {
                $data[$hash] = [[$k, $v]];
            }
            return [FALSE, NULL];
        }
    }

    private $data;
    private $count;

    private function __construct($data, int $count)
    {
        $this->data = $data;
        $this->count = $count;
    }

    // IteratorAggregate methods

    public function getIterator()
    {
        return (function() {
            foreach ($this->data as $kvs) {
                foreach ($kvs as $kv) {
                    yield $kv;
                }
            }
        })();
    }
    
    // Countable methods

    public function count() : int
    {
        return $this->count;
    }

    // ArrayAccess methods

    public function offsetExists($key)
    {
        return static::Apply($this->data, $key, 0, NULL)[0];
    }

    public function offsetGet($key)
    {
        [$exists, $value] = static::Apply($this->data, $key, 0, NULL);
        if ($exists) {
            return $value;
        }
        throw new \Exception('Key does not exist');
    }

    public function offsetSet($offset, $value)
    {
        throw new \Exception('Map is readonly');
    }

    public function offsetUnset($offset)
    {
        throw new \Exception('Map is readonly');
    }

    // Normal class methods

    public function set($key, $value) : Map
    {
        $data = $this->data;
        [$existed] = static::Apply($data, $key, 1, $value);
        return new Map($data, $this->count + ($existed ? 0 : 1));
    }

    public function filter(Callable $fnPredicate) : Map
    {
        $resultPairs = [];
        foreach ($this as [$k, $v]) {
            if ($fnPredicate($k, $v)) {
                $resultPairs[] = [$k, $v];
            }
        }
        return static::FromPairs($resultPairs);
    }

    public function mapValues(Callable $fnMap) : Map
    {
        $resultPairs = [];
        foreach ($this as [$k, $v]) {
            $resultPairs[] = [$k, $fnMap($k, $v)];
        }
        return static::FromPairs($resultPairs);
    }

    public function keys(): Vector
    {
        $result = [];
        foreach ($this as [$k]) {
            $result[] = $k;
        }
        return Vector::New($result);
    }

    public function values(): Vector
    {
        $result = [];
        foreach ($this as [$_, $v]) {
            $result[] = $v;
        }
        return Vector::New($result);
    }

    public function get($key, $default)
    {
        [$exists, $value] = static::Apply($this->data, $key, 0, NULL);
        return $exists ? $value : $default;
    }
}
