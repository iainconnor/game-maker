<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\Cornucopia\Annotations\InputTypeHint;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\GameMaker;
use IainConnor\GameMaker\ObjectInformation;

/**
 * @TODO, not complete.
 */
class OpenApi3 extends Swagger2
{
    /** @var string[] */
    public $hosts;

    /**
     * Swagger2 constructor.
     * @param string $title
     * @param null|string $version
     * @param null|string $description
     * @param string[] $hosts
     */
    public function __construct($title, $version, $description, $hosts)
    {
        parent::__construct($title, $version, $description);
        $this->hosts = $hosts;
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
            'openapi' => '3.0.0',
            'info' => [
                'version' => (string)$this->version,
                'title' => $this->title,
                'description' => $this->description . $this->branding ? (($this->description ? " " . json_decode('"\u00B7"') . " " : "") . "Generated with " . json_decode('"\u2764"') . " by GameMaker " . json_decode('"\u00B7"') . " https://github.com/iainconnor/game-maker.") : '',
            ],
            'servers' => array_map(function ($element) use ($basePath) {
                return [
                    'url' => $element . $basePath
                ];
            }, $this->hosts),
            'paths' => $this->generateJsonForControllers($controllers, $longestUniqueNamePrefix, $basePath),
            'components' => [
                'schemas' => $this->generateJsonForDefinitions($uniqueObjects, $longestUniqueNamePrefix)
            ]
        ];

        return json_encode($json, JSON_PRETTY_PRINT);
    }

    /**
     * @param ObjectInformation $object
     * @param ObjectInformation[] $allObjects
     * @param $longestCommonNamePrefix
     * @return array
     * @throws \Exception
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

        $requiredProperties = [];
        $properties = [];

        foreach ($propertiesList as $property) {
            if (is_null($property->defaultValue) && !$this->doesNullTypeExist($property->types)) {
                $requiredProperties[] = $property->variableName;
            }

            $propertySchemas = [];
            foreach ($property->types as $type) {
                if (!$this->typeIsNull($type->type)) {
                    $propertySchema = [];

                    if ($this->typeIsSimple($type->getTypeOfInterest())) {
                        $propertySchema['type'] = $this->getSwaggerType($type->getTypeOfInterest(), $longestCommonNamePrefix);
                    } else {
                        $propertySchema['$ref'] = $this->getSwaggerType($type->getTypeOfInterest(), $longestCommonNamePrefix);
                    }

                    if ($type->type == TypeHint::ARRAY_TYPE) {
                        $propertySchema = [
                            'type' => 'array',
                            'items' => $propertySchema
                        ];
                    }

                    $propertySchemas[] = $propertySchema;
                }
            }

            if (count($propertySchemas) > 1) {
                $properties[$property->variableName]['oneOf'] = $propertySchemas;
            } else if (count($propertySchemas)) {
                $properties[$property->variableName] = array_values($propertySchemas)[0];
            }
        }

        if ($definedParentClass) {
            $definedParentClassProperties = [
                'properties' => $properties
            ];

            if (count($requiredProperties)) {
                $definedParentClassProperties['required'] = $requiredProperties;
            }

            return [
                'type' => 'object',
                'allOf' => [
                    [
                        'type' => 'object'
                    ],
                    [
                        '$ref' => '#/components/schemas/' . substr($definedParentClass->uniqueName, strlen($longestCommonNamePrefix))
                    ],
                    $definedParentClassProperties
                ]
            ];
        } else {
            $typeJson = [
                'type' => 'object',
                'properties' => $properties
            ];

            if (count($requiredProperties)) {
                $typeJson['required'] = $requiredProperties;
            }

            return $typeJson;
        }
    }

    /**
     * @param string $type
     * @return bool
     */
    public function typeIsSimple($type)
    {

        return $type == \DateTime::class || parent::typeIsSimple($type);
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

                return "#/components/schemas/" . substr($type, strlen($longestCommonNamePrefix));
            }
        }

        return null;
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
        $json = parent::generateJsonForController($controller, $longestCommonNamePrefix, $longestCommonControllersPrefix, $basePath);

        foreach ($controller->endpoints as $endpoint) {
            if (count($endpoint->outputs)) {
                $relativePath = substr(parse_url($endpoint->httpMethod->path, PHP_URL_PATH), $basePath ? strlen($basePath) : 0);

                $requestBody = $this->generateJsonForRequestBody($endpoint->inputs, $longestCommonNamePrefix);

                if (!empty($requestBody)) {
                    $json[$relativePath][strtolower(GameMaker::getAfterLastSlash(get_class($endpoint->httpMethod)))] = [
                        'requestBody' => $requestBody
                    ];
                }
            }
        }

        return $json;
    }

    /**
     * @param Input[] $inputs
     * @param $longestCommonNamePrefix
     * @return array
     */
    private function generateJsonForRequestBody(array $inputs, $longestCommonNamePrefix)
    {
        $json = [];

        $bodyDescriptions = [];


        $countOfNonNullForm = 0;
        $countOfNonNullBody = 0;

        foreach ($inputs as $input) {
            if (strtolower($input->in) == 'form' || strtolower($input->in) == 'body') {
                if (!isset($input->skipDoc) || $input->skipDoc !== true) {
                    /** @var InputTypeHint $typeHint */
                    $typeHint = $input->typeHint;

                    $nonNullCount = $this->getCountOfNonNullHints($typeHint);

                    if ($nonNullCount) {
                        if (strtolower($input->in) == 'form') {
                            $countOfNonNullForm += $nonNullCount;

                            $propertySchemas = [];
                            foreach ($typeHint->types as $type) {
                                if (!$this->typeIsNull($type->type)) {
                                    $propertySchema = [];

                                    if ($this->typeIsSimple($type->getTypeOfInterest())) {
                                        $propertySchema['type'] = $this->getSwaggerType($type->getTypeOfInterest(), $longestCommonNamePrefix);
                                    } else {
                                        $propertySchema['$ref'] = $this->getSwaggerType($type->getTypeOfInterest(), $longestCommonNamePrefix);
                                    }

                                    if ($input->enum) {
                                        $propertySchema['enum'] = $input->enum;
                                    }

                                    if ($type->type == TypeHint::ARRAY_TYPE) {
                                        $propertySchema = [
                                            'type' => 'array',
                                            'items' => $propertySchema
                                        ];
                                    }

                                    $propertySchemas[] = $propertySchema;
                                }
                            }

                            if (count($propertySchemas) > 1) {
                                $json['content']['application/x-www-form-urlencoded']['schema']['properties'][$input->name]['oneOf'] = $propertySchemas;
                            } else if (count($propertySchemas)) {
                                $json['content']['application/x-www-form-urlencoded']['schema']['properties'][$input->name] = array_values($propertySchemas)[0];
                            }
                        } else {
                            if ($typeHint->description) {
                                $bodyDescriptions[] = $typeHint->description;
                            }

                            $countOfNonNullBody += $nonNullCount;
                        }
                    }
                }
            }
        }

        if (count($bodyDescriptions)) {
            $json['description'] = join(', ', $bodyDescriptions);
        }

        if ($countOfNonNullForm || $countOfNonNullBody) {
            $json['required'] = 'true';
        }

        return $json;
    }

    /**
     * Gets the number of non-null type hints.
     *
     * @param TypeHint $typeHint
     * @return int
     */
    protected function getCountOfNonNullHints(TypeHint $typeHint)
    {
        $count = 0;

        foreach ($typeHint->types as $type) {
            if (!$this->typeIsNull($type->type)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param Input[] $inputs
     * @param $longestCommonNamePrefix
     * @return array
     */
    protected function generateJsonForParameters(array $inputs, $longestCommonNamePrefix)
    {
        return [];
    }

    /**
     * @param Output[] $outputs
     * @param $longestCommonNamePrefix
     * @return array
     */
    protected function generateJsonForResponses(array $outputs, $longestCommonNamePrefix)
    {

    }
}