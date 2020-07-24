<?php declare(strict_types=1);

namespace Google\Generator;

use \Nette\PhpGenerator\PhpFile;
use \Nette\PhpGenerator\PsrPrinter;
use \Nette\PhpGenerator\Constant;
use \Nette\PhpGenerator\Property;
use \Nette\PhpGenerator\Method;
use \Nette\PhpGenerator\Parameter;
use \Google\Generator\Utils\ClassTypeProxy;
use \Google\Generator\Ast\AST;

class GapicClientGenerator
{
    public static function Generate(SourceFileContext $ctx, ServiceDetails $serviceDetails)
    {
        return (new GapicClientGenerator($ctx, $serviceDetails))->GenerateGapicClient();
    }

    private $ctx;
    private $serviceDetails;

    private function __construct(SourceFileContext $ctx, ServiceDetails $serviceDetails)
    {
        $this->ctx = $ctx;
        $this->serviceDetails = $serviceDetails;
    }

    public function GenerateGapicClient()
    {
        $file = new PhpFile();
        $file->setStrictTypes();
        $namespace = $file->addNamespace($this->serviceDetails->clientNamespace);
        $namespace->add($this->GenerateClass($namespace)->GetValue());
        return (new PsrPrinter())->printFile($file);
    }

    private function GenerateClass($namespace)
    {
        $class = new ClassTypeProxy($this->serviceDetails->gapicClientClassName);
        $class->addTrait('Google\ApiCore\GapicClientTrait');
        $class->addMember($this->ServiceName());
        $class->addMember($this->ServiceAddress());
        $class->addMember($this->ServicePort());
        $class->addMember($this->CodegenName());
        $class->addMember($this->ServiceScopes());
        $class->addMember($this->GetClientDefaults());
        $class->addMember($this->Construct());
        foreach ($this->serviceDetails->methods as $method) {
            $m = $this->RpcMethod($method);
            $class->addMember($m);
        }
        return $class;
    }

    private function ServiceName(): Constant
    {
        return (new Constant('SERVICE_NAME'))
            ->setValue($this->serviceDetails->serviceName)
            ->setComment('The name of the service.');
    }

    private function ServiceAddress(): ?Constant
    {
        // return NULL;
        return !$this->serviceDetails->defaultHost ? NULL :
            (new Constant('SERVICE_ADDRESS'))
                ->setValue($this->serviceDetails->defaultHost)
                ->setComment('The default address of the service.');
    }

    private function ServicePort(): Constant
    {
        return (new Constant('DEFAULT_SERVICE_PORT'))
            ->setValue($this->serviceDetails->defaultPort)
            ->setComment('The default port of the service.');
    }

    private function CodegenName(): Constant
    {
        return (new Constant('CODEGEN_NAME'))
            ->setValue('gapic')
            ->setComment('The name of the code generator, to be included in the agent header.');
    }

    private function ServiceScopes(): ?Property
    {
        // return NULL;
        return !$this->serviceDetails->defaultScopes ? NULL :
            (new Property('serviceScopes'))
                ->setStatic()
                ->setValue($this->serviceDetails->defaultScopes->toArray())
                ->setComment('The default scopes required by the service.');
    }

    private function GetClientDefaults(): Method
    {
        return (new Method('getClientDefaults'))
            ->setPrivate()
            ->setBody(AST::Return(AST::Array([
                'serviceName' => AST::SelfAccess($this->ServiceName()),
                'apiEndpoint' => AST::Concat(AST::SelfAccess($this->ServiceAddress()), ':', AST::SelfAccess($this->ServicePort())),
                'clientConfig' => AST::Concat(AST::__DIR__, "/../resources/{$this->serviceDetails->clientConfigFilename}"),
                'descriptorsConfigPath' => AST::Concat(AST::__DIR__, "/../resources/{$this->serviceDetails->descriptorConfigFilename}"),
                'gcpApiConfigPath' => AST::Concat(AST::__DIR__, "/../resources/{$this->serviceDetails->grpcConfigFilename}"),
                'credentialsConfig' => AST::Array([
                    'scopes' => AST::SelfAccess($this->ServiceScopes()),
                ]),
                'transportConfig' => AST::Array([
                    'rest' => AST::Array([
                        'restClientConfigPath' => AST::Concat(AST::__DIR__, "/../resources/{$this->serviceDetails->restConfigFilename}"),
                    ])
                ])
            ]))->ToString());
//             ->setBody(<<<EOC
// return [
//     'serviceName' => self::{$this->ServiceName()->getName()},
//     'apiEndpoint' => self::{$this->ServiceAddress()->getName()}.':'.self::{$this->ServicePort()->getName()},
//     'clientConfig' => __DIR__.'/../resources/language_service_client_config.json',
//     'descriptorsConfigPath' => __DIR__ . '/../resources/{$this->_serviceDetails->getDescriptorConfigFilename()}',
//     'gcpApiConfigPath' => __DIR__ . '/../resources/{$this->_serviceDetails->getGrpcConfigFilename()}',
//     'credentialsConfig' => [
//         {$defaultScopesString}
//     ],
//     'transportConfig' => [
//         'rest' => [
//             'restClientConfigPath' => __DIR__ . '/../resources/{$this->_serviceDetails->getRestConfigFilename()}_rest_client_config.php',
//         ]
//     ]
// ]
// EOC
//             );
    }

    private function Construct()
    {
        $options = (new Parameter('options'))
            ->setType('array')
            ->setDefaultValue([]);
        $clientOptions = AST::Var('clientOptions');
        return (new Method('__construct'))
            ->setPublic()
            ->setParameters([$options])
            ->setBody(
                AST::Block(
                    Ast::Assign($clientOptions, AST::ThisCall(GapicClientTrait::buildClientOptions)($options)),
                    Ast::ThisCall(GapicClientTrait::setClientOptions)($clientOptions),
                )->ToString()
            );
    }

    private function RpcMethod(MethodDetails $method)
    {
        $params = $method->requiredArgs->map(fn($x) => new Parameter($x));
        return (new Method($method->name))
            ->setPublic()
            ->setParameters($params->toArray());
    }
}
