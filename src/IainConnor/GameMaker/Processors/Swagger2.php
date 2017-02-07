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
use IainConnor\GameMaker\ObjectInformation;

class Swagger2 extends Processor
{
    /** @var string */
    public $title;

    /** @var string|null */
    public $version;

    /** @var string|null */
    public $description;

    protected $swaggerTypes = [
        'string',
        'int',
        'float',
        'bool'
    ];

    /**
     * Swagger2 constructor.
     * @param string $title
     * @param null|string $version
     * @param null|string $description
     */
    public function __construct($title, $version, $description)
    {
        $this->title = $title;
        $this->version = $version;
        $this->description = $description;
    }


    /**
     * @param array $controllers
     * @return string;
     */
    public function processControllers(array $controllers)
    {
        $json = [
            'swagger' => '2.0',
            'info' => [
                'version' => (string)$this->version,
                'title' => $this->title,
                'description' => $this->description . ($this->description ? " " . json_decode('"\u00B7"') . " " : "") . "Generated with " . json_decode('"\u2764"') . " by GameMaker " . json_decode('"\u00B7"') . " https://github.com/iainconnor/game-maker).",
            ],
            'consumes' => [
                'application/json'
            ],
            'produces' => [
                'application/json'
            ],
            'paths' => $this->generateJsonForControllers($controllers),
            'definitions' => $this->generateJsonForDefinitions($this->getUniqueObjects($controllers))
        ];

        return json_encode($json, JSON_PRETTY_PRINT);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return array
     */
    protected function generateJsonForControllers(array $controllers) {
        $this->alphabetizeControllers($controllers);

        return array_merge(array_map([$this, "generateJsonForController"], $controllers));
    }

    /**
     * @param ControllerInformation $controller
     * @return array
     */
    protected function generateJsonForController(ControllerInformation $controller) {
        $json = [];

        foreach ( $controller->endpoints as $endpoint ) {
            $json[$endpoint->httpMethod->path][strtolower(GameMaker::getAfterLastSlash(get_class($endpoint->httpMethod)))] = [
                'description' => $endpoint->description,
                'operationId' => $controller->class . "@" . $endpoint->method,
                'parameters' => $this->generateJsonForParameters($endpoint->inputs),
                'responses' => $this->generateJsonForResponses($endpoint->outputs)
            ];
        }

        return $json;
    }

    /**
     * @param Output[] $outputs
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForResponses(array $outputs) {
        $json = [];

        foreach ( $outputs as $output ) {
            /** @var OutputTypeHint $typeHint */
            $typeHint = $output->typeHint;

            if ( array_key_exists($output->statusCode, $json) || count($typeHint->types) > 1 ) {
                throw new \Exception("Sorry, as of Swagger 2.0, you can only have one response per HTTP status code. See https://github.com/OAI/OpenAPI-Specification/issues/270.");
            }



            $type = $typeHint->types[0];
            $schema = [];
            if ( $this->typeIsSimple($type->type) ) {
                $schema['type'] = $this->getSwaggerType($type->type);
            } else {
                $schema['$ref'] = $this->getSwaggerType($type->type);
            }

            if ( $type->type == TypeHint::ARRAY_TYPE ) {
                if ( $this->typeIsSimple($type->type) ) {
                    $schema['items']['type'] = $this->getSwaggerType($type->genericType);
                } else {
                    $schema['items']['$ref'] = $this->getSwaggerType($type->genericType);
                }
            }

            $json[$output->statusCode] = [
                'description' => $typeHint->description,
                'schema' => $schema
            ];
        }

        return $json;
    }

    /**
     * @param Input[] $inputs
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForParameters(array $inputs) {
        $json = [];

        foreach ($inputs as $input) {
            /** @var InputTypeHint $typeHint */
            $typeHint = $input->typeHint;

            $parameter = [
                'name' => $input->name,
                'in' => strtolower($input->in),
                'description' => $typeHint->description,
                'required' => !$this->doesNullTypeExist($typeHint->types)
            ];

            foreach ( $typeHint->types as $type ) {
                if ( $this->typeIsSimple($type->type) ) {
                    $parameter['type'][] = $this->getSwaggerType($type->type);
                } else {
                    if ( isset($parameter['schema']['$ref']) ) {
                        throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type of object parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                    }
                    $parameter['schema']['$ref'] = $this->getSwaggerType($type->type);
                }
            }

            if ( isset($parameter['type']) && count($parameter['type']) == 1 ) {
                $parameter['type'] = array_shift($parameter['type']);
            }

            if ( $input->enum ) {
                $parameter['enum'] = $input->enum;
            }

            $arrayType = $this->getArrayType($typeHint->types);
            if ( $arrayType ) {
                if ($this->typeIsSimple($arrayType->genericType)) {
                    $parameter['items']['type'] = $this->getSwaggerType($arrayType->genericType);
                } else {
                    $parameter['items']['$ref'] = $this->getSwaggerType($arrayType->genericType);
                }

                $parameter['collectionFormat'] = strtolower($input->arrayFormat);
            }

            $json[] = $parameter;
        }

        return $json;
    }

    /**
     * @param Type[] $types;
     * @return bool
     */
    protected function doesNullTypeExist(array $types) {
        foreach ($types as $type ) {
            if ( $this->typeIsNull($type->type) ) {

                return true;
            }
        }

        return false;
    }

    /**
     * @param Type[] $types;
     * @return Type|null
     */
    protected function getArrayType(array $types) {
        foreach ($types as $type ) {
            if ( $type->type == TypeHint::ARRAY_TYPE ) {

                return $type;
            }
        }

        return null;
    }

    /**
     * @param string $type
     * @return bool
     */
    protected function typeIsNull($type) {

        return $type == null || strtoupper($type) == "NULL";
    }

    /**
     * @param string $type
     * @return bool
     */
    protected function typeIsSimple($type) {

        return $type == TypeHint::ARRAY_TYPE || array_search($type, TypeHint::$basicTypes) !== false;
    }

    /**
     * @param string $type
     * @return array|string|null
     */
    protected function getSwaggerType($type) {
        if ( !$this->typeIsNull($type) ) {
            if ( $this->typeIsSimple($type) ) {

                return $type;
            } else {

                return "#/definitions/" . GameMaker::getAfterLastSlash($type);
            }
        }

        return null;
    }

    /**
     * @param ObjectInformation[] $objects
     * @return array
     */
    protected function generateJsonForDefinitions(array $objects) {
        //$this->alphabetizeObjects($objects);

        $json = [];

        foreach ( $objects as $object ) {
            $json[$object->uniqueName] = $this->generateJsonForDefinition($object, $objects);
        }

        return $json;
    }

    /**
     * @param ObjectInformation $object
     * @param ObjectInformation[] $allObjects
     * @return array
     * @throws \Exception
     */
    protected function generateJsonForDefinition(ObjectInformation $object, array $allObjects) {
        $definedParentClass = $this->getParentClass($object->class, $allObjects);

        // If there's a defined parent, we only want the properties unique to this child.
        if ( $definedParentClass ) {
            $propertiesList = $object->specificProperties;
        } else {
            $propertiesList = $object->properties;
        }

        $requiredProperties = [];
        $properties = [];

        foreach ( $propertiesList as $property ) {
            if ( !$this->doesNullTypeExist($property->types) ) {
                $requiredProperties[] = $property->variableName;
            }

            $propertySchema = [];
            foreach ( $property->types as $type ) {
                if ( $this->typeIsSimple($type->type) ) {
                    $propertySchema['type'][] = $this->getSwaggerType($type->type);
                } else {
                    if ( isset($propertySchema['$ref']) ) {
                        throw new \Exception("Sorry, as of Swagger 2.0, you can only have one type of object parameter. See https://github.com/OAI/OpenAPI-Specification/issues/458.");
                    }
                    $propertySchema['$ref'] = $this->getSwaggerType($type->type);
                }

                if ( $type->type == TypeHint::ARRAY_TYPE ) {
                    if ($this->typeIsSimple($type->genericType)) {
                        $propertySchema['items']['type'] = $this->getSwaggerType($type->genericType);
                    } else {
                        $propertySchema['items']['$ref'] = $this->getSwaggerType($type->genericType);
                    }
                }
            }

            if ( isset($propertySchema['type']) && count($propertySchema['type']) == 1 ) {
                $propertySchema['type'] = array_shift($propertySchema['type']);
            }

            $properties[$property->variableName] = $propertySchema;
        }

        if ( $definedParentClass ) {

            return [
                'type' => 'object',
                'allOf' => [
                    [
                        '$ref' => $definedParentClass
                    ],
                    [
                        'required' => $requiredProperties,
                        'properties' => $properties
                    ]
                ]
            ];
        } else {

            return [
                'type' => 'object',
                'required' => $requiredProperties,
                'properties' => $properties
            ];
        }
    }

    /**
     * @param $class
     * @param array $objects
     * @return ObjectInformation|null
     */
    protected function getParentClass($class, array $objects) {
        $parentClass = get_parent_class($class);
        if ( $parentClass !== false ) {
            /** @var ObjectInformation $object */
            foreach ( $objects as $object ) {
                if ( $object->class == $parentClass ) {

                    return $object;
                }
            }
        }

        return null;
    }

}