<?php declare(strict_types=1);

namespace Google\Generator\Utils;

use \Nette\PhpGenerator\ClassType;

class ClassTypeProxy
{
    private ClassType $classType;

    public function __construct(string $name)
    {
        $this->classType = new ClassType($name);
    }

    public function __call(string $name , array $args)
    {
        return call_user_func_array([$this->classType, $name], $args);
    }

    public function AddMember($member): void
    {
        if ($member !== NULL)
        {
            $this->classType->AddMember($member);
        }
    }

    public function GetValue(): ClassType
    {
        return $this->classType;
    }

}