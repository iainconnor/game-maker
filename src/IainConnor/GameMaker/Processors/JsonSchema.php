<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\GameMaker\ControllerInformation;

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
        $this->swaggerProcessor = new Swagger2(get_class(), 'draft-04', $description);
    }

    public function processControllers(array $controllers)
    {
        $swaggerContents = json_decode($this->swaggerProcessor->processControllers($controllers), true);
        $objects = [];

        foreach ( $swaggerContents['definitions'] as $name => $definition ) {
            $localDefinitions = [];
            foreach ( $swaggerContents['definitions'] as $compareName => $compareDefinition ) {
                if ( $name != $compareName ) {
                    $localDefinitions[$compareName] = $compareDefinition;
                }
            }

            $objects[$name] = json_encode(array_merge($definition, [
                '$schema' => "http://json-schema.org/draft-04/schema#",
                'title' => $name,
                'description' => $swaggerContents['info']['description'],
                'definitions' => $localDefinitions
            ]), JSON_PRETTY_PRINT);
        }

        return $objects;
    }

}