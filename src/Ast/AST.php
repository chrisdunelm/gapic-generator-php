<?php declare(strict_types=1);

namespace Google\Generator\Ast;

use \Nette\PhpGenerator\Constant;
use \Nette\PhpGenerator\Property;
use \Nette\PhpGenerator\Method;
use \Nette\PhpGenerator\Parameter;
use \Google\Generator\Collections\Vector;
use \Google\Generator\Collections\Map;

abstract class AST
{
    public const __DIR__ = "\0__DIR__";

    protected static function ToPhp($x)
    {
        if (is_string($x))
        {
            if (strncmp($x, "\0", 1) === 0) {
                return substr($x, 1);
            } else {
                return '"' . $x . '"';
            }
        }
        if ($x instanceof AST) {
            return $x->ToString();
        }
        if ($x instanceof Constant) {
            return $x->getName();
        }
        if ($x instanceof Property) {
            return '$' . $x->getName();
        }
        if ($x instanceof Method) {
            return $x->getName();
        }
        if ($x instanceof Parameter) {
            return '$' . $x->getName();
        }
        throw new \Exception("Cannot generator that PHP: '" . get_class($x) . "' {$x}");
    }

    public static function Block(...$statements): AST
    {
        return new class(Vector::New($statements)) extends AST
        {
            public function __construct($statements)
            {
                $this->statements = $statements;
            }
            public function ToString()
            {
                return $this->statements
                    ->map(fn($x) => static::ToPhp($x))
                    ->join("\n");
            }
        };
    }

    public static function Return(AST $value): AST
    {
        return new class($value) extends AST
        {
            public function __construct($value)
            {
                $this->value = $value;
            }
            public function ToString()
            {
                return "return {$this->value->ToString()}";
            }
        };
    }

    public static function Array(array $kvs): AST
    {
        return new class(Map::New($kvs)) extends AST
        {
            public function __construct($kvs)
            {
                $this->kvs = $kvs;
            }
            public function ToString()
            {
                $e = $this->kvs
                    ->filter(fn($k, $v) => $v !== NULL)
                    ->mapValues(fn($k, $v) => "'{$k}' => " . static::ToPhp($v))
                    ->values()
                    ->join(', ');
                return "[{$e}]";
            }
        };
    }

    public static function SelfAccess($accessee) : ?AST
    {
        return !$accessee ? NULL : new class($accessee) extends AST
        {
            public function __construct($accessee)
            {
                $this->accessee = $accessee;
            }
            public function ToString()
            {
                return 'self::' . static::ToPhp($this->accessee);
            }
        };
    }

    public static function ThisCall($callee)
    {
        return new class($callee) extends AST
        {
            public function __construct($callee)
            {
                $this->callee = $callee;
            }
            public function __invoke(...$args)
            {
                $this->args = Vector::New($args);
                return $this;
            }
            public function ToString()
            {
                $args = $this->args ? $this->args->map(fn($x) => static::ToPhp($x))->join(', ') : '';
                return '$this->' . static::ToPhp($this->callee) . "({$args})";
            }
        };
    }

    public static function Concat(...$items): ?AST
    {
        $items = Vector::New($items);
        $null = $items->any(fn($x) => is_null($x));
        return $null ? NULL : new class($items) extends AST
        {
            public function __construct($items)
            {
                $this->items = $items;
            }
            public function ToString()
            {
                return $this->items->map(fn($x) => static::ToPhp($x))->join(' . ');
            }
        };
    }

    public static function Var(string $name): AST
    {
        return new class($name) extends AST
        {
            public function __construct($name) { $this->name = $name; }
            public function ToString()
            {
                return '$' . $this->name;
            }
        };
    }

    public static function Assign(AST $to, $from): AST
    {
        return new class($to, $from) extends AST
        {
            public function __construct($to, $from) { $this->to = $to; $this->from = $from; }
            public function ToString()
            {
                return static::ToPhp($this->to) . " = " . static::ToPhp($this->from);
            }
        };
    }

    abstract public function ToString();
}
