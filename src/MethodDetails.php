<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Protobuf\Internal\MethodDescriptorProto;
use \Google\Generator\Collections\Vector;
use \Google\Generator\Utils\ProtoHelpers;
use \Google\Generator\Utils\CustomOptions;

class MethodDetails
{
    public static function Create(ServiceDetails $svc, MethodDescriptorProto $desc): MethodDetails
    {
        return new MethodDetails($svc, $desc);
    }

    private static function GetFields($msgDesc): Vector
    {
        $msgProto = $msgDesc->underlyingProto;
        $fieldDescs = Vector::New($msgDesc->getField());
        $fieldProtos = Vector::New($msgProto->getField());
        return Vector::Zip($fieldDescs, $fieldProtos)->map(function ($x) {
            $x[0]->underlyingProto = $x[1];
            return $x[0];
        });
    }

    public string $name; // readonly
    public Vector $requiredArgs; // readonly
    public Vector $optionalArgs; // readonly

    private function __construct($svc, $desc)
    {
        $catalog = $svc->catalog;
        $this->name = $desc->getName();
        $inputMsgDesc = $catalog->msgsByFullname[$desc->getInputType()];
        $allFields = static::GetFields($inputMsgDesc); // Vector::New($inputMsgDesc->getField());
        //print(get_class($allFields[0])."\n");
        // 2 = REQUIRED (hacky)
        $requiredFields = $allFields
            ->filter(fn($x) => ProtoHelpers::GetCustomOptionRepeatedInt($x, CustomOptions::GOOGLE_API_FIELDBEHAVIOR)->contains(2));
        $optionalFields = $allFields
            ->filter(fn($x) => !ProtoHelpers::GetCustomOptionRepeatedInt($x, CustomOptions::GOOGLE_API_FIELDBEHAVIOR)->contains(2));
        $this->requiredArgs = $requiredFields->map(fn($x) => $x->getName());
        $this->optionalArgs = $optionalFields->map(fn($x)=> $x->getName());
    }
}
