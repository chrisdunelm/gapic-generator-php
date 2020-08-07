<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Protobuf\Internal\MethodDescriptorProto;
use \Google\Generator\Collections\Vector;
use \Google\Generator\Utils\ProtoHelpers;
use \Google\Generator\Utils\CustomOptions;
use \Google\Protobuf\Internal\Descriptor;

class MethodDetails
{
    public static function Create(ServiceDetails $svc, MethodDescriptorProto $desc): MethodDetails
    {
        return new MethodDetails($svc, $desc);
    }

    private static function GetFields(Descriptor $msgDesc): Vector // Returns Vector<FieldDetails>
    {
        $msgProto = $msgDesc->underlyingProto;
        $fieldDescs = Vector::New($msgDesc->getField());
        $fieldProtos = Vector::New($msgProto->getField());
        return Vector::Zip($fieldDescs, $fieldProtos)->map(function ($x) {
            $x[0]->underlyingProto = $x[1];
            return FieldDetails::Create($x[0]);
        });
    }

    public string $name; // readonly
    public Vector $requiredFields; // readonly Vector<FieldDetails>
    public Vector $optionalFields; // readonly Vector<FieldDetails>
    public Type $requestType; // readonly
    public Type $responseType; // readonly
    public string $setter; // readonly
    public Vector $docLines; // readonly Vector<string>

    private function __construct($svc, $desc)
    {
        $catalog = $svc->catalog;
        $this->name = $desc->getName();
        $inputMsgDesc = $catalog->msgsByFullname[$desc->getInputType()];
        $outputMsgDesc = $catalog->msgsByFullname[$desc->getOutputType()];
        $allFields = static::GetFields($inputMsgDesc);
        // 2 = REQUIRED - TODO: Use enums properly
        $this->requiredFields = $allFields->filter(fn($x) => $x->isRequired);
        $this->optionalFields = $allFields->filter(fn($x) => !$x->isRequired);
        $this->requestType = Type::FromMessage($inputMsgDesc);
        $this->responseType = Type::FromMessage($outputMsgDesc);
        $this->docLines = $desc->leadingComments;
    }
}
