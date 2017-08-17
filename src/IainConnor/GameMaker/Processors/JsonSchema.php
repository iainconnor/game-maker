<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\Type;
use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\NamingConventions\CamelToSnake;

class JsonSchema extends Processor
{
    /** @var Swagger2 */
    protected $swaggerProcessor;

    /**
     * JsonSchema constructor.
     * @param string|null $description
     */
    public function __construct($description = null)
    {
        $this->swaggerProcessor = new Swagger2(get_class(), new CamelToSnake(), 'draft-04', $description);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return mixed;
     */
    public function processControllers(array $controllers)
    {
        $swaggerContents = json_decode($this->swaggerProcessor->processControllers($controllers), true);
        $objects = [];

        // Add object definitions.
        foreach ($swaggerContents['definitions'] as $name => $definition) {
            $localDefinitions = [];
            foreach ($swaggerContents['definitions'] as $compareName => $compareDefinition) {
                if ($name != $compareName) {
                    $localDefinitions[$compareName] = $compareDefinition;
                }
            }

            $objects[$name] = json_encode(array_merge($definition, [
                '$schema' => "http://json-schema.org/draft-04/schema#",
                'title' => $name,
                'description' => $swaggerContents['info']['description'] ?: '',
                'definitions' => $localDefinitions
            ]), JSON_PRETTY_PRINT);
        }

        // Add simple types.
        foreach ($controllers as $controller) {
            foreach ($controller->endpoints as $endpoint) {
                foreach ($endpoint->outputs as $output) {
                    /** @var Type $type */
                    foreach ($output->typeHint->types as $type) {
                        if (!$this->swaggerProcessor->typeIsNull($type->type) && $this->swaggerProcessor->typeIsSimple($type->type)) {
                            if ($type->type == TypeHint::ARRAY_TYPE) {
                                $swaggerType = array_key_exists($type->genericType, $this->swaggerProcessor->swaggerTypeMap) ? $this->swaggerProcessor->swaggerTypeMap[$type->genericType] : $type->genericType;
                                $name = $type->genericType . TypeHint::ARRAY_TYPE_SHORT;
                                $objects[$name] = json_encode([
                                    '$schema' => "http://json-schema.org/draft-04/schema#",
                                    'title' => $name,
                                    'type' => 'array',
                                    'items' => [
                                        'type' => $swaggerType
                                    ]
                                ], JSON_PRETTY_PRINT);
                            } else {
                                $name = $type->type;
                                $swaggerType = array_key_exists($type->type, $this->swaggerProcessor->swaggerTypeMap) ? $this->swaggerProcessor->swaggerTypeMap[$type->type] : $type->type;
                                $objects[$name] = json_encode([
                                    '$schema' => "http://json-schema.org/draft-04/schema#",
                                    'title' => $name,
                                    'type' => $swaggerType
                                ], JSON_PRETTY_PRINT);
                            }
                        }
                    }
                }
            }
        }

        return $objects;
    }

}