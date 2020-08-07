<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Generator\Collections\Set;
use \Nette\PhpGenerator\PhpNamespace;

class SourceFileContext
{
    private string $namespace;
    private Set $uses;

    public function __construct()
    {
        $this->namespace = '';
        $this->uses = Set::New();
    }

    public function SetNamespace(string $namespace)
    {
        $this->namespace = $namespace;
    }

    public function Type(Type $type)
    {
        if ($type->isClass()) {
            if ($type->getNamespace() !== $this->namespace) {
                // No 'use' required if type is in the current namespace
                $fullname = $type->getFullname();
                $this->uses = $this->uses->add($fullname);
            }
        }
        return new ResolvedType($type->name);
    }

    public function AddUses(PhpNamespace $namespace): void
    {
        foreach ($this->uses as $use) {
            $namespace->addUse($use);
        }
    }
}
