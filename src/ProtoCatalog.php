<?php

namespace Google\Generator;

use \Google\Generator\Collections\Vector;
use \Google\Generator\Collections\Map;
use \Google\Generator\Utils\ProtoHelpers;
use \Google\Protobuf\Internal\Descriptor;

class ProtoCatalog
{
    public Map $msgsByFullname; // readonly Map<string, Descriptor>
    
    private static function MsgPlusNested(Descriptor $desc): Vector
    {
        return Vector::New($desc->getNestedType())->flatMap(fn($x) => static::MsgPlusNested($x))->append($desc);
    }

    // $fileDescs : Vector<Google\Protobuf\Internal\FileDescriptorProto>
    public function __construct(Vector $fileDescs)
    {
        $topLevelMsgs = $fileDescs->flatMap(fn($fileDesc) =>
            Vector::New($fileDesc->getMessageType())->map(fn($msgProto) =>
                ProtoHelpers::AddProto(Descriptor::buildFromProto($msgProto, $fileDesc, ''), $msgProto)));
        $allMsgs = $topLevelMsgs->flatMap(fn($x) => static::MsgPlusNested($x));
        $this->msgsByFullname = $allMsgs->toMap(fn($x) => $x->getFullName());
    }
}
