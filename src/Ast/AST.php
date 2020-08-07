<?php declare(strict_types=1);

namespace Google\Generator\Ast;

use \Nette\PhpGenerator\Constant;
use \Nette\PhpGenerator\Property;
use \Nette\PhpGenerator\Method;
use \Nette\PhpGenerator\Parameter;
use \Google\Generator\Collections\Vector;
use \Google\Generator\Collections\Map;
use \Google\Generator\ResolvedType;

abstract class AST
{
    public const __DIR__ = "\0__DIR__";

    protected static function ToPhp($x): string
    {
        if (is_string($x))
        {
            if (strncmp($x, "\0", 1) === 0) {
                return substr($x, 1);
            } else {
                return "'{$x}'";
            }
        } elseif ($x instanceof AST) {
            return $x->ToCode();
        } elseif ($x instanceof ResolvedType) {
            return strval($x);
        } elseif ($x instanceof Constant) {
            return $x->getName();
        } elseif ($x instanceof Property) {
            return '$' . $x->getName();
        } elseif ($x instanceof Method) {
            return $x->getName();
        } elseif ($x instanceof Parameter) {
            return '$' . $x->getName();
        } else {
            throw new \Exception("Cannot generator that PHP: '" . get_class($x) . "' {$x}");
        }
    }

    public static function Block(...$code): AST
    {
        // Every statement is an AST instance
        $code = Vector::New($code)
            ->flatten()
            ->filter(fn($x) => !is_null($x));
        return new class($code) extends AST
        {
            public function __construct($code)
            {
                $this->code = $code;
            }
            public function ToCode(): string
            {
                return $this->code
                    ->map(fn($x) => $x->ToCode(). ';')
                    ->join('');
            }
        };
    }

    public static function Return(Expression $expr): AST
    {
        return new class($expr) extends AST
        {
            public function __construct($expr)
            {
                $this->expr = $expr;
            }
            public function ToCode(): string
            {
                return 'return ' . static::ToPhp($this->expr);
            }
        };
    }

    public static function Array(array $array): Expression
    {
        $keyValues = Vector::New(array_map(fn($v, $k) => [$k, $v], $array, array_keys($array)))
            ->filter(fn($x) => !is_null($x[1]));
        return new class($keyValues) extends Expression
        {
            public function __construct($keyValues)
            {
                $this->keyValues = $keyValues;
            }
            public function ToCode(): string
            {
                $isAssocArray = $this->keyValues->map(fn($x) => $x[0])->toArray() !== range(0, count($this->keyValues) - 1);
                $items = $isAssocArray ?
                    $this->keyValues->map(fn($x) => static::ToPhp($x[0]) . ' => ' . static::ToPhp($x[1])) :
                    $this->keyValues->map(fn($x) => static::ToPhp($x[1]));
                $items = $items->map(fn($x) => "{$x},\n")->join('');
                return "[\n{$items}]";
            }
        };
    }

    public static function SelfAccess($accessee) : ?Expression
    {
        return !$accessee ? null : new class($accessee) extends Expression
        {
            public function __construct($accessee)
            {
                $this->accessee = $accessee;
            }
            public function ToCode(): string
            {
                return 'self::' . static::ToPhp($this->accessee);
            }
        };
    }

    public static function ThisCall($callee): Expression
    {
        return new class($callee) extends Expression
        {
            public function __construct($callee)
            {
                $this->callee = $callee;
                $this->args = Vector::New();
            }
            public function __invoke(...$args)
            {
                $this->args = Vector::New($args);
                return $this;
            }
            public function ToCode(): string
            {
                $args = $this->args->map(fn($x) => static::ToPhp($x))->join(', ');
                return '$this->' . static::ToPhp($this->callee) . "({$args})";
            }
        };
    }

    public static function Call($obj, $callee): Expression
    {
        return new class($obj, $callee) extends Expression
        {
            public function __construct($obj, $callee)
            {
                $this->obj = $obj;
                $this->callee = $callee;
                $this->args = Vector::New();
            }
            public function __invoke(...$args)
            {
                $this->args = Vector::New($args);
                return $this;
            }
            public function ToCode(): string
            {
                $args = $this->args->map(fn($x) => static::ToPhp($x))->join(', ');
                $deref = $this->obj instanceof ResolvedType ? '::' : '->';
                return static::ToPhp($this->obj) . $deref . static::ToPhp($this->callee) . "({$args})";
            }
        };
    }

    public static function Concat(...$items): ?Expression
    {
        $items = Vector::New($items);
        $null = $items->any(fn($x) => is_null($x));
        return $null ? NULL : new class($items) extends Expression
        {
            public function __construct($items)
            {
                $this->items = $items;
            }
            public function ToCode(): string
            {
                return $this->items->map(fn($x) => static::ToPhp($x))->join(' . ');
            }
        };
    }

    public static function Var(string $name): Expression
    {
        return new class($name) extends Expression
        {
            public function __construct($name)
            {
                $this->name = $name;
            }
            public function ToCode(): string
            {
                return '$' . $this->name;
            }
        };
    }

    public static function Assign(AST $to, $from): Expression
    {
        return new class($to, $from) extends Expression
        {
            public function __construct($to, $from)
            {
                $this->to = $to;
                $this->from = $from;
            }
            public function ToCode(): string
            {
                return static::ToPhp($this->to) . " = " . static::ToPhp($this->from);
            }
        };
    }

    public static function New(ResolvedType $typeName): Expression
    {
        return new class($typeName) extends Expression
        {
            public function __construct($typeName)
            {
                $this->typeName = $typeName;
            }
            public function ToCode(): string
            {
                return "new {$this->typeName}()";
            }
        };
    }

    public static function Class(ResolvedType $typeName): Expression
    {
        return new class($typeName) extends Expression
        {
            public function __construct($typeName)
            {
                $this->typeName = $typeName;
            }
            public function ToCode(): string
            {
                return "{$this->typeName}::class";
            }
        };
    }

    public static function If($condition): AST
    {
        return new class($condition) extends AST
        {
            public function __construct($condition)
            {
                $this->condition = $condition;
            }
            public function then(...$code)
            {
                $this->then = AST::Block(...$code);
                return $this;
            }
            public function ToCode(): string
            {
                return
                    'if (' . static::ToPhp($this->condition) . ") {\n"
                    . static::ToPhp($this->then) . ";\n"
                    . '}';
            }
        };
    }

    public static function IsSet($var): Expression
    {
        return new class($var) extends Expression
        {
            public function __construct($var)
            {
                $this->var = $var;
            }
            public function ToCode(): string
            {
                return 'isset(' . static::ToPhp($this->var) . ')';
            }
        };
    }

    public static function ArrayAccess($array, $index): Expression
    {
        return new class($array, $index) extends Expression
        {
            public function __construct($array, $index)
            {
                $this->array = $array;
                $this->index = $index;
            }
            public function ToCode(): string
            {
                return static::ToPhp($this->array) . '[' . static::ToPhp($this->index) . ']';
            }
        };
    }

    public static function Try(...$code): AST
    {
        return new class(AST::Block(...$code)) extends AST
        {
            public function __construct($code)
            {
                $this->code = $code;
                $this->catches = [];
                $this->finally = null;
            }
            public function finally(...$code)
            {
                $this->finally = AST::Block(...$code);
                return $this;
            }
            public function ToCode(): string
            {
                $code = 'try {' . $this->code->ToCode() . '}';
                if ($this->finally) {
                    $code .= 'finally {' . $this->finally->ToCode() . '}';
                }
                return $code;
            }
        };
    }

    public static function Literal($value): Expression
    {
        return new class(strval($value)) extends Expression
        {
            public function __construct($value)
            {
                $this->value = $value;
            }
            public function ToCode(): string
            {
                return $this->value;
            }
        };
    }

    public abstract function ToCode(): string;
}
