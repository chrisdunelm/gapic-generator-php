<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Protobuf\Internal\FieldDescriptor;
use \Google\Protobuf\Internal\GPBType;
use \Google\Generator\Utils\ProtoHelpers;
use \Google\Generator\Utils\CustomOptions;
use \Google\Generator\Type;
use \Nette\PhpGenerator\Method;
use \Google\Generator\Collections\Vector;

class FieldDetails
{
    public static function Create(FieldDescriptor $desc): FieldDetails
    {
        return new FieldDetails($desc);
    }

    public int $type; // readonly - e.g. GPBType::MESSAGE
    public ?Type $messageType; // readonly - If $type is a message, then this is the PHP type
    public string $name; // readonly
    public Method $setter; // readonly
    public bool $isRequired; // readonly
    public Vector $docLines; // readonly

    private function __construct(FieldDescriptor $desc)
    {
        $this->type = $desc->getType();
        $this->messageType = null;
        switch ($this->type) {
            case GPBType::MESSAGE:
                $this->messageType = Type::FromMessage($desc->getMessageType());
                break;
            case GPBType::ENUM:
                throw new \Exception('Not yet implemented');
                break;
        }
        $this->name = $desc->getName();
        $this->setter = new Method($desc->getSetter());
        // 2 = REQUIRED - TODO: Use proper enum
        $this->isRequired = ProtoHelpers::GetCustomOptionRepeatedInt($desc, CustomOptions::GOOGLE_API_FIELDBEHAVIOR)->contains(2);
        $this->docLines = $desc->underlyingProto->leadingComments;
    }
}
