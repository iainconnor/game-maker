<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\Cornucopia\Annotations\InputTypeHint;
use IainConnor\Cornucopia\Annotations\OutputTypeHint;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\Type;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\Output;
use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\GameMaker;
use IainConnor\GameMaker\NamingConventions\NamingConvention;
use IainConnor\GameMaker\ObjectInformation;

class Swagger2 extends Processor
{
    /** @var string */
    public $title;

    /** @var string|null */
    public $version;

    /** @var string|null */
    public $description;

    /** @var NamingConvention */
    public $namingConvention;

    /** @var string */
    public $host;

    /** @var string[] */
    public $schemes;

    public $swaggerTypeMap = [
        'int' => 'integer',
        'float' => 'number',
        'bool' => 'boolean'
    ];

    public $swaggerInMap = [
        'form' => 'formData'
    ];

    public $swaggerSupportedArrayFormats = [
        'CSV',
        'SSV',
        'TSV',
        'PIPES',
        'MULTI'
    ];

    /**
     * @param $title
     * @param NamingConvention $namingConvention
     * @param null $version
     * @param null $description
     * @param string $host
     * @param array $schemes
     */
    public function __construct($title, NamingConvention $namingConvention, $version = null, $description = null, $host = null, array $schemes = [])
    {
        $this->title = $title;
        $this->version = $version;
        $this->description = $description;
        $this->namingConvention = $namingConvention;
        $this->host = $host;
        $this->schemes = $schemes;
    }

    /**
     * @param array $controllers
     * @return string;
     */
    public function processControllers(array $controllers)
    {
        $basePath = $this->extractBasePathFromControllers($controllers);

        $uniqueObjects = array_filter(GameMaker::getUniqueObjectInControllers($controllers), function (ObjectInformation $objectInformation) {
            return !isset($objectInformation->skipDoc) || $objectInformation->skipDoc !== true;
        });

        $uniqueNames = array_map(function ($element) {
            return $element->uniqueName;
        }, $uniqueObjects);

        $longestUniqueNamePrefix = $this->getLongestCommonPrefix($uniqueNames, '\\');

        $json = [
            'swagger' => '2.0',
            'info' => [
                'version' => (string)$this->version,
                'title' => $this->title,
                'description' => $this->description . $this->branding ? (($this->description ? " " . json_decode('"\u00B7"') . " " : "") . "Generated with " . json_decode('"\u2764"') . " by GameMaker " . json_decode('"\u00B7"') . " https://github.com/iainconnor/game-maker.") : '',
            ],
            'host' => $this->host ?: $this->extractHostFromControllers($controllers),
            'schemes' => !empty($this->schemes) ? $this->schemes : $this->extractSchemesFromControllers($controllers),
            'consumes' => [
                'application/json',
                'application/x-www-form-urlencoded'
            ],
            'produces' => [
                'application/json'
            ],
            'paths' => $this->generateJsonForControllers($controllers, $longestUniqueNamePrefix, $basePath),
            'definitions' => array_merge($this->generateJsonForDefinitions($uniqueObjects, $longestUniqueNamePrefix), $this->generateJsonForDefinitionsForBodyParameters($controllers, $longestUniqueNamePrefix))
        ];

        if ($basePath) {
            $json['basePath'] = $basePath;
        }

        return json_encode($json, JSON_PRETTY_PRINT);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return string
     */
    protected function extractBasePathFromControllers(array $controllers)
    {
        $paths = [];

        foreach ($controllers as $controller) {
            foreach ($controller->endpoints as $endpoint) {
                $path = parse_url($endpoint->httpMethod->path, PHP_URL_PATH);
                if (array_search($path, $paths) === false) {
                    $paths[] = $path;
                }
            }
        }

        return rtrim($this->getLongestCommonPrefix($paths), '/');
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return string
     * @throws \Exception
     */
    protected function extractHostFromControllers(array $controllers)
    {
        $host = null;

        foreach ($controllers as $controller) {
            foreach ($controller->endpoints as $endpoint) {
                $port = $this->getPortIfUnique($endpoint->httpMethod->path);

                if ($host == null) {
                    $host = parse_url($endpoint->httpMethod->path, PHP_URL_HOST) . ($port ? ':' . $port : '');
                }

                if ($host != parse_url($endpoint->httpMethod->path, PHP_URL_HOST) . ($port ? ':' . $port : '')) {
                    throw new \Exception("Sorry, Swagger 2.0 requires all endpoints be on the same host.");
                }
            }
        }

        return $host;
    }

    /**
     * Returns the port number present in the given URL if it's non-typical/default.
     *
     * @param string $url
     * @return string|null
     */
    protected function getPortIfUnique($url)
    {
        $port = parse_url($url, PHP_URL_PORT);

        if (!$port) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($port == 80 && strtolower($scheme) == 'http') {
            return null;
        }

        if ($port == 443 && strtolower($scheme) == 'https') {
            return null;
        }

        return $port;
    }

    /**
     * @param array $controllers
     * @return array
     */
    protected function extractSchemesFromControllers(array $controllers)
    {
        $schemes = [];

        foreach ($controllers as $controller) {
            foreach ($controller->endpoints as $endpoint) {
                $scheme = parse_url($endpoint->httpMethod->path, PHP_URL_SCHEME);
                if (array_search($scheme, $schemes) === false) {
                    $schemes[] = $scheme;
                }
            }
        }

        return $schemes;
    }

    /**
     * @param ControllerInformation[] $controllers
     * @param $longestCommonNamePrefix
     * @param string|null $basePath
     * @return array
     */
    protected function generateJsonForControllers(array $controllers, $longestCommonNamePrefix, $basePath = null)
    {
        $json = [];

        $this->alphabetizeControllers($controllers);

        $longestCommonControllerPrefix = $this->getLongestCommonPrefix(array_map(function (ControllerInformation $controllerInformation) {
            return $controllerInformation->class;
        }, $controllers), '\\');

        foreach ($controllers as $controller) {
            $json = array_merge($json, $this->generateJsonForController($controller, $longestCommonNamePrefix, $longestCommonControllerPrefix, $basePath));
        }

        return $json;
    }

    /**
     * @param ControllerInformation $controller
     * @param $longestCommonNamePrefix
     * @param $longestCommonControllersPrefix
     * @param string|null $basePath
     * @return array
     */
    protected function generateJsonForController(ControllerInformation $controller, $longestCommonNamePrefix, $longestCommonControllersPrefix, $basePath = null)
    {
        $json = [];

        foreach ($controller->endpoints as $endpoint) {
            if (count($endpoint->outputs)) {
                $relativePath = substr(parse_url($endpoint->httpMethod->path, PHP_URL_PATH), $basePath ? strlen($basePath) : 0);

                $json[$relativePath][strtolower(GameMaker::getAfterLastSlash(get_class($endpoint->httpMethod)))] = [
                    'description' => ($endpoint->description ?: ''),
                    'operationId' => $endpoint->httpMethod->friendlyName ? (str_replace(' ', '', ucwords($endpoint->httpMethod->friendlyName))) : (substr($controller->class, strlen($longestCommonControllersPrefix)) . "@" . $endpoint->method),
                    'parameters' => $this->generateJsonForParameters($endpoint->inputs, $longestCommonNamePrefix),
                    'responses' => $this->generateJsonForResponses($endpoint->outputs, $longestCommonNamePrefix),
                    'tags' => $this->generateJsonForTags($endpoint->tags)
                ];
            }
        }

        return $json;
    }

    /**
     * @param Input[] $inputs
     * @param $longestCommonNamePrefix
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForParameters(array $inputs, $longestCommonNamePrefix)
    {
        $json = [];

        $bodyInputs = $this->getBodyInputs($inputs);
        $hasManyBodyInputs = count($bodyInputs) > 1;
        if ($hasManyBodyInputs) {
            $bodyInputDefinitionName = $this->generateNameForBodyInputs($bodyInputs);
            $bodyRequired = false;
            foreach ($bodyInputs as $bodyInput) {
                if (!$this->doesNullTypeExist($bodyInput->typeHint->types)) {
                    $bodyRequired = true;
                    break;
                }
            }

            $json[] = [
                'name' => 'body',
                'in' => 'body',
                'required' => $bodyRequired,
                'schema' => [
                    '$ref' => '#/definitions/' . $bodyInputDefinitionName
                ]
            ];
        }

        foreach ($inputs as $input) {
            if (!isset($input->skipDoc) || $input->skipDoc !== true) {
                if (!($hasManyBodyInputs && $input->in == 'BODY')) {
                    /** @var InputTypeHint $typeHint */
                    $typeHint = $input->typeHint;

                    $parameter = [
                        'name' => $input->name,
                        'in' => array_key_exists(strtolower($input->in), $this->swaggerInMap) ? $this->swaggerInMap[strtolower($input->in)] : strtolower($input->in),
                        'description' => $typeHint->description ?: '',
                        'required' => is_null($typeHint->defaultValue) && !$this->doesNullTypeExist($typeHint->types)
                    ];

                    if (!is_null($typeHint->defaultValue) || (is_null($typeHint->defaultValue) && $this->doesNullTypeExist($typeHint->types))) {
                        $parameter['default'] = is_null($typeHint->defaultValue) ? null : $typeHint->defaultValue;
                    }

                    foreach ($typeHint->types as $type) {
                        if (!$this->typeIsNull($type->type)) {
                            if ($this->typeIsSimple($type->type)) {
                                if (isset($parameter['type'])) {
                                    throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type of object parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                                }

                                $parameter['type'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                            } else {
                                if (isset($parameter['schema']['$ref'])) {
                                    throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type of object parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                                }

                                $parameter['schema']['$ref'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                            }
                        }
                    }

                    if ($input->enum) {
                        $parameter['enum'] = $input->enum;
                    }

                    $arrayType = $this->getArrayType($typeHint->types);
                    if ($arrayType && $input->arrayFormat != 'BRACKETS' && $input->in == 'BODY') {
                        // Unsupported array format, treat it as just a string.
                        $parameter['type'] = 'string';
                        unset($parameter['enum']);
                    } else if ($arrayType && (($input->arrayFormat == 'BRACKETS' && $input->in == 'BODY') || (array_search($input->arrayFormat, $this->swaggerSupportedArrayFormats) !== false))) {
                        if ($this->typeIsSimple($arrayType->genericType)) {
                            $parameter['items']['type'] = $this->getSwaggerType($arrayType->genericType, $longestCommonNamePrefix);
                        } else {
                            $parameter['items']['$ref'] = $this->getSwaggerType($arrayType->genericType, $longestCommonNamePrefix);
                        }

                        $parameter['collectionFormat'] = strtolower($input->arrayFormat);

                        if (isset($parameter['enum'])) {
                            $parameter['items']['enum'] = $parameter['enum'];
                            unset($parameter['enum']);
                        }
                    } else if ($arrayType) {
                        // Unsupported array format, treat it as just a string.
                        $parameter['type'] = 'string';
                    }

                    if ($input->in == 'BODY') {
                        unset($parameter['default']);
                        unset($parameter['collectionFormat']);

                        if (isset($parameter['type'])) {
                            $parameter['schema']['type'] = $parameter['type'];
                            unset($parameter['type']);
                            if (isset($parameter['items'])) {
                                $parameter['schema']['items'] = $parameter['items'];
                                unset($parameter['items']);
                            }
                        }

                        if (isset($parameter['enum'])) {
                            $parameter['schema']['enum'] = $parameter['enum'];
                            unset($parameter['enum']);
                        }
                    }

                    $json[] = $parameter;
                }
            }
        }

        return $json;
    }

    /**
     * @param Type[] $types ;
     * @return bool
     */
    protected function doesNullTypeExist(array $types)
    {
        foreach ($types as $type) {
            if ($this->typeIsNull($type->type)) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function typeIsNull($type)
    {

        return $type == null || strtoupper($type) == "NULL";
    }

    /**
     * @param string $type
     * @return bool
     */
    public function typeIsSimple($type)
    {

        return $type == TypeHint::ARRAY_TYPE || array_search($type, TypeHint::$basicTypes) !== false;
    }

    /**
     * @param string $type
     * @param $longestCommonNamePrefix
     * @return array|null|string
     */
    protected function getSwaggerType($type, $longestCommonNamePrefix)
    {
        if (!$this->typeIsNull($type)) {
            if ($this->typeIsSimple($type)) {
                if (array_key_exists($type, $this->swaggerTypeMap)) {

                    return $this->swaggerTypeMap[$type];
                }

                return $type;
            } else {

                return "#/definitions/" . substr($type, strlen($longestCommonNamePrefix));
            }
        }

        return null;
    }

    /**
     * @param Type[] $types ;
     * @return Type|null
     */
    protected function getArrayType(array $types)
    {
        foreach ($types as $type) {
            if ($type->type == TypeHint::ARRAY_TYPE) {

                return $type;
            }
        }

        return null;
    }

    /**
     * @param Output[] $outputs
     * @param $longestCommonNamePrefix
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForResponses(array $outputs, $longestCommonNamePrefix)
    {
        $json = [];

        foreach ($outputs as $output) {
            if (!isset($output->skipDoc) || $output->skipDoc !== true) {
                /** @var OutputTypeHint $typeHint */
                $typeHint = $output->typeHint;

                if (array_key_exists($output->statusCode, $json) || count($typeHint->types) > 1) {
                    throw new \Exception("Sorry, as of Swagger 2.0, you can only have one response per HTTP status code. See https://github.com/OAI/OpenAPI-Specification/issues/270.");
                }

                $type = $typeHint->types[0];
                $schema = [];
                if (!$this->typeIsNull($type->type)) {
                    if ($this->typeIsSimple($type->type)) {
                        $schema['type'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                    } else {
                        $schema['$ref'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                    }

                    if ($type->type == TypeHint::ARRAY_TYPE) {
                        if (!$this->typeIsNull($type->genericType)) {
                            if ($this->typeIsSimple($type->genericType)) {
                                $schema['items']['type'] = $this->getSwaggerType($type->genericType, $longestCommonNamePrefix);
                            } else {
                                $schema['items']['$ref'] = $this->getSwaggerType($type->genericType, $longestCommonNamePrefix);
                            }
                        }
                    }
                }

                $json[$output->statusCode]['description'] = $typeHint->description ?: '';

                if (!empty($schema)) {
                    $json[$output->statusCode]['schema'] = $schema;
                }
            }
        }

        return $json;
    }

    /**
     * @param string[] $tags
     * @return array|\string[]
     */
    protected function generateJsonForTags(array $tags)
    {

        return $tags;
    }

    /**
     * @param ControllerInformation[] $controllers
     * @param $longstCommonNamePrefix
     * @return array
     */
    protected function generateJsonForDefinitionsForBodyParameters(array $controllers, $longstCommonNamePrefix)
    {
        $json = [];

        foreach ($controllers as $controller) {
            foreach ($controller->endpoints as $endpoint) {
                $bodyInputs = $this->getBodyInputs($endpoint->inputs);
                if (count($bodyInputs) > 1) {
                    /** @var TypeHint[] $bodyInputTypeHints */
                    $bodyInputTypeHints = array_map(function (Input $input) {
                        return $input->typeHint;
                    }, $bodyInputs);

                    $json[$this->generateNameForBodyInputs($endpoint->inputs)] = $this->generateJsonForListOfProperties($bodyInputTypeHints, $longstCommonNamePrefix);
                }
            }
        }

        return $json;
    }

    /**
     * @param ObjectInformation[] $objects
     * @param $longestCommonNamePrefix
     * @return array
     */
    protected function generateJsonForDefinitions(array $objects, $longestCommonNamePrefix)
    {
        $this->alphabetizeObjects($objects);

        $json = [];

        foreach ($objects as $object) {
            $json[substr($object->uniqueName, strlen($longestCommonNamePrefix))] = $this->generateJsonForDefinition($object, $objects, $longestCommonNamePrefix);
        }

        return $json;
    }

    /**
     * @param Input[] $inputs
     * @return int|string
     */
    protected function generateNameForBodyInputs(array $inputs)
    {
        $nameParts = [];

        foreach ($inputs as $input) {
            $nameParts[$input->variableName] = array_map(function (Type $type) {
                return $type->type == TypeHint::ARRAY_TYPE ? ('Array' . ($type->genericType ? 'Of' . ucfirst(GameMaker::getAfterLastSlash($type->genericType)) . 's' : '')) : ucfirst(GameMaker::getAfterLastSlash($type->type)) ?: ucfirst(TypeHint::NULL_TYPE);
            }, $input->typeHint->types);
        }

        // Alphabetize for consistency.
        ksort($nameParts);

        $generatedName = 'BodyWith';
        foreach ($nameParts as $name => $parts) {
            $generatedName .= ucfirst($name) . 'As' . join('Or', $parts);
        }

        return $generatedName;
    }

    /**
     * @param Input[] $inputs
     * @return int
     */
    protected function getCountOfInBodyInputs(array $inputs)
    {
        return count($this->getBodyInputs($inputs));
    }

    /**
     * @param Input[] $inputs
     * @return Input[]
     */
    protected function getBodyInputs(array $inputs)
    {
        return array_filter($inputs, function (Input $element) {
            return (!isset($element->skipDoc) || !$element->skipDoc) && $element->in == 'BODY';
        });
    }

    /**
     * @param TypeHint[] $propertiesList
     * @param string $longestCommonNamePrefix
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForListOfProperties(array $propertiesList, $longestCommonNamePrefix)
    {
        $requiredProperties = [];
        $properties = [];

        foreach ($propertiesList as $property) {
            if (is_null($property->defaultValue) && !$this->doesNullTypeExist($property->types)) {
                $requiredProperties[] = $property->variableName;
            }

            $propertySchema = [];
            if (!is_null($property->defaultValue) || (is_null($property->defaultValue) && $this->doesNullTypeExist($property->types))) {
                $propertySchema['default'] = is_null($property->defaultValue) ? null : $property->defaultValue;
            }

            foreach ($property->types as $type) {
                if (!$this->typeIsNull($type->type)) {
                    if ($this->typeIsSimple($type->type)) {
                        if (isset($propertySchema['type'])) {
                            throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type per parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                        }

                        $propertySchema['type'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                    } else {
                        if (isset($propertySchema['$ref'])) {
                            throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type of object parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                        }

                        $propertySchema['$ref'] = $this->getSwaggerType($type->type, $longestCommonNamePrefix);
                    }

                    if ($type->type == TypeHint::ARRAY_TYPE) {
                        if (!$this->typeIsNull($type->genericType)) {
                            if ($this->typeIsSimple($type->genericType)) {
                                $propertySchema['items']['type'] = $this->getSwaggerType($type->genericType, $longestCommonNamePrefix);
                            } else {
                                $propertySchema['items']['$ref'] = $this->getSwaggerType($type->genericType, $longestCommonNamePrefix);
                            }
                        }
                    }
                }
            }

            $properties[$property->variableName] = $propertySchema;
        }

        $typeJson = [
            'type' => 'object',
            'properties' => $properties
        ];

        if (count($requiredProperties)) {
            $typeJson['required'] = $requiredProperties;
        }

        return $typeJson;
    }

    /**
     * @param ObjectInformation $object
     * @param ObjectInformation[] $allObjects
     * @param $longestCommonNamePrefix
     * @return array
     */
    protected function generateJsonForDefinition(ObjectInformation $object, array $allObjects, $longestCommonNamePrefix)
    {
        $definedParentClass = $this->getParentClass($object->class, $allObjects);

        // If there's a defined parent, we only want the properties unique to this child.
        if ($definedParentClass) {
            $propertiesList = $object->specificProperties;
        } else {
            $propertiesList = $object->properties;
        }

        $typeJson = $this->generateJsonForListOfProperties($propertiesList, $longestCommonNamePrefix);

        if ($definedParentClass) {
            unset($typeJson['type']);
            $typeJson = [
                'type' => 'object',
                'allOf' => [
                    [
                        '$ref' => '#/definitions/' . substr($definedParentClass->uniqueName, strlen($longestCommonNamePrefix))
                    ],
                    $typeJson
                ]
            ];
        }

        return $typeJson;
    }


    /**
     * @param $class
     * @param array $objects
     * @return ObjectInformation|null
     */
    protected function getParentClass($class, array $objects)
    {
        $parentClass = get_parent_class($class);
        if ($parentClass !== false) {
            /** @var ObjectInformation $object */
            foreach ($objects as $object) {
                if ($object->class == $parentClass) {

                    return $object;
                }
            }
        }

        return null;
    }

}