<?php declare(strict_types=1);

namespace Google\Generator;

use \Google\Protobuf\Internal\ServiceDescriptorProto;
use \Google\Generator\Utils\ProtoHelpers;
use \Google\Generator\Utils\CustomOptions;
use \Google\Generator\Utils\Helpers;
use \Google\Generator\Collections\Vector;

class ServiceDetails {
    public ProtoCatalog $catalog; // readonly
    public string $clientNamespace; // readonly
    public string $serviceFullName; // readonly
    public string $gapicClientClassName; // readonly
    public string $serviceName; // readonly
    public string $defaultHost; // readonly
    public int $defaultPort; // readonly
    public Vector $defaultScopes; // readonly
    public string $descriptorConfigFilename; // readonly
    public string $grpcConfigFilename; // readonly
    public string $restConfigFilename; // readonly
    public string $clientConfigFilename; // readonly
    public Vector $methods; // readonly

    public function __construct(ProtoCatalog $catalog, string $namespace, string $package, ServiceDescriptorProto $desc)
    {
        $this->catalog = $catalog;
        $this->clientNamespace = "{$namespace}\Gapic";
        $this->serviceFullName = "{$namespace}.{$desc->getName()}";
        $this->gapicClientClassName = "{$desc->getName()}GapicClient";
        $this->serviceName = "{$package}.{$desc->getName()}";
        $this->defaultHost = ProtoHelpers::GetCustomOptionString($desc, CustomOptions::GOOGLE_API_DEFAULTHOST);
        $this->defaultPort = 443;
        $defaultScopes = ProtoHelpers::GetCustomOptionString($desc, CustomOptions::GOOGLE_API_OAUTHSCOPES);
        $this->defaultScopes = !$defaultScopes ? NULL : Vector::New(explode(',', $defaultScopes))->map(fn($x) => trim($x));
        $this->descriptorConfigFilename = Helpers::ToSnakeCase($desc->getName()) . '_descriptor_config.php';
        $this->grpcConfigFilename = Helpers::ToSnakeCase($desc->getName()) . '_grpc_config.json';
        $this->restConfigFilename = Helpers::ToSnakeCase($desc->getName()) . '_rest_client_config.php';
        $this->clientConfigFilename = Helpers::ToSnakeCase($desc->getName()) . '_client_config.json';
        $this->methods = Vector::New($desc->getMethod())->map(fn($x) => MethodDetails::Create($this, $x));
    }
}
