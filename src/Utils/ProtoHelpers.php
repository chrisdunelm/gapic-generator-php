<?php declare(strict_types=1);

namespace Google\Generator\Utils;

use \Google\Protobuf\Internal\FileDescriptorProto;
use \Google\Protobuf\Internal\Message;
use \Google\Protobuf\Internal\CodedInputStream;
use \Google\Protobuf\Internal\HasPublicDescriptorTrait;
use \Google\Protobuf\Internal\GPBWire;
use \Google\Generator\Collections\Vector;

class ProtoHelpers
{
    public static function GetNamespace(FileDescriptorProto $desc): string
    {
        if ($desc->hasOptions())
        {
            $opts = $desc->getOptions();
            if ($opts->hasPhpNamespace())
            {
                return $opts->getPhpNamespace();
            }
        }
        return implode('\\', explode('.', $desc->getPackage()));
    }

    public static function AddProto($desc, $proto)
    {
        $desc->underlyingProto = $proto;
        return $desc;
    }

    // Return type is dependant on option type. Either string, int, or Vector of string or int,
    // or null if not repeated and value doesn't exist. Repeated returns empty vector if not exists.
    private static function GetCustomOptionRaw(Message $message, int $optionId, bool $repeated)
    {
        static $messageUnknown;
        if (!$messageUnknown)
        {
            $ref = new \ReflectionClass('Google\Protobuf\Internal\Message');
            $messageUnknown = $ref->getProperty('unknown');
            $messageUnknown->setAccessible(TRUE);
        }

        $values = [];
        if ($message->hasOptions())
        {
            $opts = $message->getOptions();
            $unknown = $messageUnknown->getValue($opts);
            if ($unknown)
            {
                $unknownStream = new CodedInputStream($unknown);
                while (($tag = $unknownStream->readTag()) !== 0)
                {
                    $value = 0;
                    switch (GPBWire::getTagWireType($tag)) {
                        case GPBWire::WIRETYPE_VARINT:
                            $unknownStream->readVarint32($value);
                            break;
                        case GPBWire::WIRETYPE_LENGTH_DELIMITED:
                            $len = 0;
                            $unknownStream->readVarintSizeAsInt($len);
                            $unknownStream->readRaw($len, $value);
                            break;
                        default:
                            throw new \Exception('Cannot read option tag');
                    }
                    if (GPBWire::getTagFieldNumber($tag) === $optionId) {
                        if ($repeated) {
                            $values[] = $value;
                        } else {
                            return $value;
                        }
                    }
                }
            }
        }
        return $repeated ? Vector::New($values) : null;
    }

    private static function ConformMessage($message): Message
    {
        if (isset($message->underlyingProto)) {
            $message = $message->underlyingProto;
        }
        if (!($message instanceof Message)) {
            throw new \Exception('Can only get custom option of Message or HasPublicDescriptorTrait');
        }
        return $message;
    }

    public static function GetCustomOptionString($message, int $optionId): ?string
    {
        return static::GetCustomOptionRaw(static::ConformMessage($message), $optionId, false);
    }

    public static function GetCustomOptionRepeatedInt($message, int $optionId): Vector
    {
        return static::GetCustomOptionRaw(static::ConformMessage($message), $optionId, true);
    }
}
