<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
error_reporting(E_ALL);

use Google\Generator\CodeGenerator;

$opts = getopt('', ['descriptor:', 'package:']);
$descBytes = stream_get_contents(fopen($opts['descriptor'], 'rb'));
$package = $opts['package'];

$z = CodeGenerator::GenerateFromDescriptor($descBytes, $package);
print("here goes...\n");
foreach ($z as $zz)
{
    print("{$zz}\n");
}

return;

$codeReqBytes = ""; //fread(STDIN, 100000);
while (!feof(STDIN)) {
    $codeReqBytes .= fread(STDIN, 8192);
}

$codeReq = new Google\Protobuf\Compiler\CodeGeneratorRequest();
$codeReq->mergeFromString($codeReqBytes);

$msg = $codeReq->getFileToGenerate()[0];

$codeResp = new Google\Protobuf\Compiler\CodeGeneratorResponse();
$codeResp->setError("This is an error from PHP: " . $msg);
$codeRespBytes = $codeResp->serializeToString();
fwrite(STDOUT, $codeRespBytes);

return;

$bytes = file_get_contents("./test.desc");

$desc = new Google\Protobuf\Internal\FileDescriptorSet();
$desc->mergeFromString($bytes);

// print($desc->hasFile());
// print("\n");
foreach ($desc->getFile() as $fileDesc) {
    // print($fileDesc->getName());
    // print("\n");
    if ($fileDesc->getName() == "test.proto") {
        $desc = $fileDesc;
        break;
    }
}
//$desc = $desc->getFile()[0];

print($desc->getName());
print("\n");
print($desc->getPackage());
print("\n");

$opts = $desc->getOptions();
print(count($opts->getUninterpretedOption()));
print(" <- file getun()\n");
print($opts->getPhpNamespace() . " <- php_namespace\n");

$ref = (new ReflectionClass(get_class($opts)))->getParentClass();
$unknown = $ref->getProperty('unknown');
$unknown->setAccessible(TRUE);
$zz = $unknown->getValue($opts);
//print($zz . " <- zz\n");

$st = new Google\Protobuf\Internal\CodedInputStream($zz);
$tag = $st->readTag();
print($tag . " <- tag\n");
$len = 0;
$st->readVarintSizeAsInt($len);
print($len . " <- length\n");

$resDesc = new Google\Api\ResourceDescriptor();
$resDesc->parseFromStream($st);
print($resDesc->getType() . " <- type\n");
print($resDesc->getPattern()[0] . " <- pattern[0]\n");

// foreach ($desc->getMessageType() as $msg) {
//     print($msg->getName());
//     print("\n");
//     //$serviceDetails = new ServiceDetails("12");
// }

// $namespace = $desc->getPackage(); // Not right

// foreach ($desc->getService() as $svc) {
//     print($svc->getName());
//     print("\n");
//     $service = new ServiceDetails($namespace, $svc);
//     print($service->getServiceFullName());
//     print("\n");
//     print($svc->hasOptions());
//     print(" <- hasOptions()\n");
//     $opts = $svc->getOptions();
//     print($opts->hasUninterpretedOption());
//     print(" <- hasUninterpretedOption()\n");
//     $o = $opts->getUninterpretedOption();
//     print($o->count());
//     print("\n");
//     foreach ($opts->getUninterpretedOption() as $un) {
//         print("!!!");
//         print($un);
//         print("\n");
//     }
// }

// print($desc->hasName());
// print("\n");
// print($desc->hasField());
// print("\n");
// foreach ($desc->getField() as $field) {
//     print($field);
//     print("\n");
// }

// $class = new Nette\PhpGenerator\ClassType('Demo');

// $class
// 	->setFinal()
// 	->setExtends(ParentClass::class)
// 	->addImplement(Countable::class)
// 	->addTrait(Nette\SmartObject::class)
// 	->addComment("Description of class.\nSecond line\n")
// 	->addComment('@property-read Nette\Forms\Form $form');

// // to generate PHP code simply cast to string or use echo:
// echo $class;

?>
