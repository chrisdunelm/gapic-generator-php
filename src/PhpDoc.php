<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Generator\Collections\Vector;
use \Google\Generator\Collections\Map;
use \Google\Generator\Ast\AST;
use \Nette\PhpGenerator\Parameter;
use \Google\Protobuf\Internal\GPBType;

abstract class PhpDoc
{
    public static function Block(...$items): PhpDoc
    {
        return new class(Vector::New($items)->flatten()->filter(fn($x) => !is_null($x))) extends PhpDoc
        {
            public function __construct($items)
            {
                $this->items = $items;
                $this->isBlock = true;
            }
            protected function ToLines(Map $info): Vector
            {
                $info = Map::New();
                foreach ($this->items as $item)
                {
                    $info = $item->PreProcess($info);
                }
                return Vector::Zip($this->items, $this->items->skip(1)->append(null))->flatMap(function($x) use($info) {
                    [$item, $next] = $x;
                    $result = $item->toLines($info);
                    if (!is_null($next) && !(isset($item->isParam) && isset($next->isParam))) {
                        $result = $result->append('');
                    }
                    return $result;
                });
                return $this->items
                    ->flatMap(fn($x) => $x->ToLines($info)->append(''));
            }
        };
    }

    public static function NewLine(): PhpDoc
    {
        return new class extends PhpDoc
        {
            public function __construct()
            {
                $this->isNewLine = true;
            }
            protected function ToLines(Map $info): Vector
            {
                return Vector::New();
            }
        };
    }

    public static function PreFormattedText(Vector $lines): PhpDoc
    {
        return new class($lines) extends PhpDoc
        {
            public function __construct($lines)
            {
                $this->lines = $lines;
            }
            protected function ToLines(Map $info): Vector
            {
                return $this->lines;
            }
        };
    }

    public static function Text(...$parts): PhpDoc
    {
        return new class(Vector::New($parts)) extends PhpDoc
        {
            public function __construct($parts)
            {
                $this->parts = $parts;
            }
            protected function ToLines(Map $info): Vector
            {
                $lineLen = 80;
                $lines = Vector::New();
                $line = '';

                $commitLine = function() use(&$lines, &$line) {
                    if ($line !== '') {
                        $lines = $lines->append($line);
                        $line = '';
                    }
                };
                $add = function($s) use(&$lines, &$line, $lineLen, $commitLine) {
                    if (strlen($line) + 1 + strlen($s) > $lineLen && $line !== '') {
                        $commitLine();
                    }
                    if ($line === '') {
                        $line = $s;
                    } else {
                        $line .= ' ' . $s;
                    }
                };

                foreach ($this->parts as $part) {
                    if (is_string($part)) {
                        $words = explode(' ', $part);
                        foreach ($words as $word) {
                            if ($word !== '') {
                                $add($word);
                            }
                        }
                    } elseif ($part instanceof ResolvedType) {
                        $word = '{@see ' . $part . '}';
                        $add($word);
                    } elseif ($part instanceof AST) {
                        $word = '{@see ' . $part->ToCode() . '}';
                        $add($word);
                    } elseif ($part instanceof PhpDoc) {
                        $commitLine();
                        foreach ($part->ToLines(Map::New()) as $line) {
                            $commitLine();
                        }
                    } else {
                        throw new \Exception('Cannot convert part to text');
                    }
                }
                $commitLine();
                return $lines;
            }
        };
    }

    public static function ParamOrType(string $tag, Vector $types, string $name, PhpDoc $description): PhpDoc
    {
        return new class($tag, $types->join('|'), $name, $description) extends PhpDoc
        {
            private const K_NAME = 'param_name';
            private const K_TYPE = 'param_type';
            public function __construct($tag, $types, $name, $description)
            {
                $this->tag = $tag;
                $this->types = $types;
                $this->name = $name;
                $this->description = $description;
                $this->isParam = true;
            }
            protected function PreProcess(Map $info): Map
            {
                if ($this->tag === 'param') {
                    $info = $info->set(static::K_TYPE, max($info->get(static::K_TYPE, 0), strlen($this->types)));
                    $info = $info->set(static::K_NAME, max($info->get(static::K_NAME, 0), strlen($this->name)));
                }
                return $info;
            }
            protected function ToLines(Map $info): Vector
            {
                $lines = $this->description->toLines(Map::New());
                if ($this->tag === 'param') {
                    $typeLen = $info->get(static::K_TYPE, 0);
                    $nameLen = $info->get(static::K_NAME, 0);
                    $types = str_pad($this->types, $typeLen);
                    $intro = "@{$this->tag} {$types} \${$this->name}";
                    $introPad = str_repeat(' ', $nameLen - strlen($this->name));
                    if (isset($this->description->isBlock)) {
                        $indent = '    ';
                        return Vector::New(["{$intro}{$introPad} {"])
                            ->concat($lines->map(fn($x) => $indent . $x))
                            ->append('}');
                    } else {
                        return $this->OutdentDescription($intro . $introPad);
                    }
                } else {
                    $indent = str_repeat(' ', strlen("@{$this->tag} "));
                    return Vector::New(["@{$this->tag} {$this->types} \${$this->name}"])
                        ->concat($lines->map(fn($x) => $indent . $x));
                }
            }
        };
    }

    public static function Param(Vector $types, string $name, PhpDoc $description): PhpDoc
    {
        return static::ParamOrType('param', $types, $name, $description);
    }

    public static function ParamFromField(SourceFileContext $ctx, FieldDetails $field)
    {
        $type = $ctx->Type(Type::FromField($field));
        return static::Param(Vector::New([$type]), $field->name, static::PreFormattedText($field->docLines));
    }

    public static function ParamFromParameter(Parameter $parameter, PhpDoc $description)
    {
        return static::Param(Vector::New([$parameter->getType()]), $parameter->getName(), $description);
    }

    public static function Type(Vector $types, string $name, PhpDoc $description): PhpDoc
    {
        return static::ParamOrType('type', $types, $name, $description);
    }

    public static function TypeFromField(SourceFileContext $ctx, FieldDetails $field)
    {
        $type = $ctx->Type(Type::FromField($field));
        return static::Type(Vector::New([$type]), $field->name, static::PreFormattedText($field->docLines));
    }

    public static function Example(?AST $ast, ?PhpDoc $intro = null): PhpDoc
    {
        return new class($ast, $intro) extends PhpDoc
        {
            public function __construct($ast, $intro)
            {
                $this->ast = $ast;
                $this->intro = $intro;
            }
            protected function ToLines(Map $info): Vector
            {
                if (is_null($this->ast)) {
                    return Vector::New();
                } else {
                    $code = "<?php\n{$this->ast->ToCode()}";
                    $lines = Vector::New(explode("\n", Formatter::Format($code)));
                    $lines = $lines->skip(3)->skipLast(1)->filter(fn($x) => $x !== '')->prepend('```')->append('```');
                    $introText = is_null($this->intro) ? Vector::New() : $this->intro->ToLines(Map::New());
                    if (count($introText) > 0) {
                        $lines = $introText->concat($lines);
                    }
                    return $lines;
                }
            }
        };
    }

    public static function Return(ResolvedType $type, ?PhpDoc $description = null): PhpDoc
    {
        return new class($type) extends PhpDoc
        {
            public function __construct($type)
            {
                $this->type = $type;
            }
            protected function ToLines(Map $info): Vector
            {
                return $this->OutdentDescription("@return {$this->type}");
            }
        };
    }

    public static function Throws(ResolvedType $type, ?PhpDoc $description = null): PhpDoc
    {
        return new class($type, $description) extends PhpDoc
        {
            public function __construct($type, $description)
            {
                $this->type = $type;
                $this->description = $description;
            }
            protected function ToLines(Map $info): Vector
            {
                return $this->OutdentDescription("@throws {$this->type}");
            }
        };
    }

    public static function Experimental(): PhpDoc
    {
        return new class extends PhpDoc
        {
            protected function ToLines(Map $info): Vector
            {
                return Vector::New(['@experimental']);
            }
        };
    }

    protected final function OutdentDescription($intro): Vector
    {
        if (!isset($this->description) || is_null($this->description)) {
            return Vector::New([trim($intro)]);
        } else {
            $lines = $this->description->ToLines(Map::New());
            $linesPad = str_repeat(' ', strlen($intro));
            return count($lines) === 0 ? Vector::New([trim($intro)]) :
                $lines->take(1)->map(fn($x) => "{$intro} {$x}")
                    ->concat($lines->skip(1)->map(fn($x) => "{$linesPad} {$x}"));
        }
    }

    protected function PreProcess(Map $info): Map
    {
        return $info;
    }

    abstract protected function ToLines(Map $info): Vector;

    public function ToCode(): string
    {
        $lines = $this->ToLines(Map::New());
        $code = $lines->join("\n");
        // print($code);
        return $code;
    }
}
