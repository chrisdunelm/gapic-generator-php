<?php declare(strict_types=1);

namespace Google\Generator;

use \Nette\PhpGenerator\PhpFile;
use \Nette\PhpGenerator\PhpNamespace;
use \Nette\PhpGenerator\PsrPrinter;
use \Nette\PhpGenerator\Constant;
use \Nette\PhpGenerator\Property;
use \Nette\PhpGenerator\Method;
use \Nette\PhpGenerator\Parameter;
use \Nette\PhpGenerator\ClassType;
use \Google\Generator\Utils\ClassTypeProxy;
use \Google\Generator\Ast\AST;
use \Google\Generator\Collections\Vector;
use \Google\Protobuf\Internal\GPBType;
use \Google\ApiCore\RetrySettings;
use \Google\ApiCore\Transport\RestTransport;
use \Google\ApiCore\Transport\GrpcTransport;
use \Google\ApiCore\Transport\TransportInterface;
use \Google\ApiCore\CredentialsWrapper;
use \Google\Auth\FetchAuthTokenInterface;

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
        $this->ctx->SetNamespace($namespace->getName());
        $namespace->add($this->GenerateClass($namespace));
        $this->ctx->AddUses($namespace);
        return (new PsrPrinter())->printFile($file);
    }

    private function GenerateClass(PhpNamespace $namespace): ClassType
    {
        $class = new ClassTypeProxy($this->serviceDetails->gapicClientClassName);
        $class->setComment(PhpDoc::Block(
            PhpDoc::PreFormattedText($this->serviceDetails->docLines->take(1)
                ->map(fn($x) => "Service Description: {$x}")
                ->concat($this->serviceDetails->docLines->skip(1))),
            PhpDoc::PreFormattedText(Vector::New([
                'This class provides the ability to make remote calls to the backing service through method',
                'calls that map to API methods. Sample code to get started:'
            ])),
            PhpDoc::Example($this->serviceDetails->methods->take(1)->map(fn($x) => $this->RpcMethodExample($x))->firstOrNull()),
            PhpDoc::Experimental(),
        )->ToCode());
        $class->addTrait($this->ctx->Type(Type::FromName(\Google\ApiCore\GapicClientTrait::class)));
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
        return $class->GetValue();
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
        return is_null($this->serviceDetails->defaultHost) ? NULL :
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
        return is_null($this->serviceDetails->defaultScopes) ? NULL :
            (new Property('serviceScopes'))
                ->setStatic()
                ->setValue($this->serviceDetails->defaultScopes->toArray())
                ->setComment('The default scopes required by the service.');
    }

    private function GetClientDefaults(): Method
    {
        return (new Method('getClientDefaults'))
            ->setPrivate()
            ->setBody(AST::Block(AST::Return(AST::Array([
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
            ])))->ToCode());
    }

    private function Construct()
    {
        $ctx = $this->ctx;
        $options = (new Parameter('options'))
            ->setType('array')
            ->setDefaultValue([]);
        $clientOptions = AST::Var('clientOptions');
        return (new Method('__construct'))
            ->setComment(PhpDoc::Block(
                PhpDoc::Text('Constructor.'),
                PhpDoc::ParamFromParameter($options, PhpDoc::Block(
                    PhpDoc::Text('Optional. Options for configuring the service API wrapper.'),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::string())]), 'serviceAddress',
                        PhpDoc::Text('**Deprecated**. This option will be removed in a future major release.',
                            'Please utilize the `$apiEndpoint` option instead.')),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::string())]), 'apiEndpoint',
                        PhpDoc::Text('The address of the API remote host. May optionally include the port, formatted',
                            "as \"<uri>:<port>\". Default '{$this->serviceDetails->defaultHost}:{$this->serviceDetails->defaultPort}'.")),
                    PhpDoc::Type(Vector::New([
                        $ctx->Type(Type::string()),
                        $ctx->Type(Type::array()),
                        $ctx->Type(Type::FromName(FetchAuthTokenInterface::class)),
                        $ctx->Type(Type::FromName(CredentialsWrapper::class))
                    ]), 'credentials',
                        PhpDoc::Text('The credentials to be used by the client to authorize API calls. This option',
                            'accepts either a path to a credentials file, or a decoded credentials file as a PHP array.', PhpDoc::NewLine(),
                            '*Advanced usage*: In addition, this option can also accept a pre-constructed',
                            $ctx->Type(Type::FromName(FetchAuthTokenInterface::class)),
                            'object or',
                            $ctx->Type(Type::FromName(CredentialsWrapper::class)),
                            'object. Note that when one of these objects are provided, any settings in $credentialsConfig will be ignored.')),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::array())]), 'credentialsConfig',
                        PhpDoc::Text('Options used to configure credentials, including auth token caching, for the client.',
                            'For a full list of supporting configuration options, see',
                            AST::Call($ctx->Type(Type::FromName(CredentialsWrapper::class)), new Method('build')))),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::bool())]), 'disableRetries',
                        PhpDoc::Text('Determines whether or not retries defined by the client configuration should be',
                            'disabled. Defaults to `false`.')),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::string()), $ctx->Type(Type::array())]), 'clientConfig',
                        PhpDoc::Text('Client method configuration, including retry settings. This option can be either a',
                            'path to a JSON file, or a PHP array containing the decoded JSON data.',
                            'By default this settings points to the default client config file, which is provided',
                            'in the resources folder.')),
                    PhpDoc::Type(Vector::New([
                        $ctx->Type(Type::string()),
                        $ctx->Type(Type::FromName(TransportInterface::class))
                    ]), 'transport',
                        PhpDoc::Text('The transport used for executing network requests. May be either the string `rest`',
                            'or `grpc`. Defaults to `grpc` if gRPC support is detected on the system.',
                            '*Advanced usage*: Additionally, it is possible to pass in an already instantiated',
                            $ctx->Type(Type::FromName(TransportInterface::class)),
                            'object. Note that when this object is provided, any settings in `$transportConfig`, and any `$apiEndpoint`',
                            'setting, will be ignored.')),
                    PhpDoc::Type(Vector::New([$ctx->Type(Type::array())]), 'transportConfig',
                        PhpDoc::Text('Configuration options that will be used to construct the transport. Options for',
                            'each supported transport type should be passed in a key for that transport. For example:',
                            PhpDoc::Example(AST::Block(
                                AST::Assign(AST::Var('transportConfig'), AST::Array([
                                    'grpc' => AST::Array(['...' => '...']),
                                    'rest' => AST::Array(['...' => '...']),
                                ])))),
                            'See the', AST::Call($ctx->Type(Type::FromName(GrpcTransport::class)), new Method('build')),
                            'and', AST::Call($ctx->Type(Type::FromName(RestTransport::class)), new Method('build')),
                            'methods for the supported options.'))
                )),
                PhpDoc::Throws($this->ctx->Type(Type::FromName(\Google\ApiCore\ValidationException::class))),
                PhpDoc::Experimental()
            )->ToCode())
            ->setPublic()
            ->setParameters([$options])
            ->setBody(
                AST::Block(
                    Ast::Assign($clientOptions, AST::ThisCall(GapicClientTrait::buildClientOptions)($options)),
                    Ast::ThisCall(GapicClientTrait::setClientOptions)($clientOptions),
                )->ToCode()
            );
    }

    private function RpcMethod(MethodDetails $method): Method
    {
        $retrySettingsType = Type::FromName(RetrySettings::class);
        $requiredParams = $method->requiredFields->map(fn($x) => new Parameter($x->name));
        $optionalParams = (new Parameter('optionalArgs'))->setType('array')->setDefaultValue([]);
        $request = AST::Var('request');
        return (new Method($method->name))
            ->setComment(PhpDoc::Block(
                PhpDoc::PreFormattedText($method->docLines),
                PhpDoc::Example($this->RpcMethodExample($method), PhpDoc::Text('Sample code:')),
                $method->requiredFields->map(fn($x) => PhpDoc::ParamFromField($this->ctx, $x)),
                PhpDoc::ParamFromParameter($optionalParams, PhpDoc::Block(
                    PhpDoc::Text('Optional.'),
                    $method->optionalFields->map(fn($x) => PhpDoc::TypeFromField($this->ctx, $x)),
                    PhpDoc::Type(
                        Vector::New([$this->ctx->Type($retrySettingsType), 'array']),
                        'retrySettings', PhpDoc::Text(
                            'Retry settings to use for this call. Can be a ', $this->ctx->Type($retrySettingsType),
                            ' object, or an associative array of retry settings parameters. See the documentation on ',
                            $this->ctx->Type($retrySettingsType), ' for example usage.'))
                )),
                PhpDoc::Return($this->ctx->Type($method->responseType)),
                PhpDoc::Throws($this->ctx->Type(Type::FromName(\Google\ApiCore\ApiException::class)),
                    PhpDoc::Text('if the remote call fails')),
                PhpDoc::Experimental(),
            )->ToCode())
            ->setPublic()
            ->setParameters($requiredParams->append($optionalParams)->toArray())
            ->setBody(
                AST::Block(
                    AST::Assign($request, AST::New($this->ctx->Type($method->requestType))),
                    Vector::Zip($method->requiredFields, $requiredParams, fn($f, $p) => AST::Call($request, $f->setter)($p)),
                    $method->optionalFields->map(fn($x) =>
                        AST::If(AST::IsSet(AST::ArrayAccess($optionalParams, $x->name)))->then(
                            AST::Call($request, $x->setter)(AST::ArrayAccess($optionalParams, $x->name))
                        )
                    ),
                    AST::Return(AST::Call(AST::ThisCall(GapicClientTrait::startCall)(
                        $method->name,
                        AST::Class($this->ctx->Type($method->responseType)),
                        $optionalParams,
                        $request
                    ), PromiseInterface::wait))
                )->ToCode()
            );
    }

    private function RpcMethodExample(MethodDetails $method): AST
    {
        $serviceClient = AST::Var($this->serviceDetails->clientVarName);
        $clientTypeName = $this->ctx->Type(Type::FromName("{$this->serviceDetails->clientNamespace}\\{$this->serviceDetails->gapicClientClassName}"));
        $callVars = $method->requiredFields->map(fn($x) => AST::Var($x->name));
        return AST::Block(
            AST::Assign($serviceClient, AST::New($clientTypeName)),
            AST::Try(
                Vector::Zip($callVars, $method->requiredFields, fn($var, $f) => AST::Assign($var, $this->DefaultAst($f))),
                AST::Call($serviceClient, new Method($method->name))(...$callVars)
            )->finally(
                AST::Call($serviceClient, GapicClientTrait::close)
            )
        );
    }

    private function DefaultAst(FieldDetails $field): AST
    {
        switch ($field->type) {
            case GPBType::INT32: return AST::Literal(0);
            case GPBType::MESSAGE: return AST::New($this->ctx->Type($field->messageType));
            default: throw new \Exception('Cannot generate that default AST!');
        }
    }
}
