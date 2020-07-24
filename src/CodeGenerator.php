<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Generator\Collections\Vector;
use \Google\Generator\Collections\Set;
use \Google\Protobuf\Internal\FileDescriptorSet;
use \Google\Protobuf\Internal\FileDescriptor;
use \Google\Generator\Utils\ProtoHelpers;

class CodeGenerator
{
    // $descriptorBytes : binary descriptor
    // $package : string proto package name to generate
    public static function GenerateFromDescriptor(string $descriptorBytes, string $package)
    {
        $descSet = new FileDescriptorSet();
        $descSet->mergeFromString($descriptorBytes);
        $descriptors = Vector::New($descSet->getFile());
        $filesToGenerate = $descriptors
            ->filter(fn($x) => $x->getPackage() === $package)
            ->map(fn($x) => $x->getName());
        yield from static::Generate($descriptors, $filesToGenerate);
    }

    // $descriptors : Vector<FileDescriptorProto> of all files
    // $filesToGenerate : Vector<string> of files to generate
    public static function Generate(Vector $descriptors, Vector $filesToGenerate)
    {
        // Note: Cannot use FileDescriptor, as it doesn't provide access to enough of the underlying proto
        $filesToGenerateSet = $filesToGenerate->toSet();
        $byPackage = $descriptors
            ->filter(fn($x) => $filesToGenerateSet[$x->getName()])
            ->groupBy(fn($x) => $x->getPackage());
        if (count($byPackage) === 0) {
            throw new \Exception('No packages specified to build');
        }
        foreach ($byPackage as [$_, $singlePackageFileDescs]) {
            $namespaces = $singlePackageFileDescs
                ->map(fn($x) => ProtoHelpers::GetNamespace($x))
                ->distinct();
            if (count($namespaces) > 1) {
                throw new \Exception('All files in the same package must have the same PHP namespace');
            }
            $catalog = new ProtoCatalog($descriptors);
            yield from static::GeneratePackage($catalog, $namespaces[0], $singlePackageFileDescs);
        }
    }

    // $namespace : string
    // $fileDescs : Collection<FileDescriptorProto>
    private static function GeneratePackage(ProtoCatalog $catalog, string $namespace, Vector $fileDescs)
    {
        foreach ($fileDescs as $fileDesc)
        {
            foreach ($fileDesc->getService() as $service)
            {
                $serviceDetails = new ServiceDetails($catalog, $namespace, $fileDesc->getPackage(), $service);
                $ctx = SourceFileContext::Create();
                $code = GapicClientGenerator::Generate($ctx, $serviceDetails);
                $filename = "";
                yield $code;
            }
        }
    }
}
