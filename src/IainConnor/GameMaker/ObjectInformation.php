<?php


namespace IainConnor\GameMaker;


use IainConnor\Cornucopia\Annotations\TypeHint;

class ObjectInformation
{
    /**
     * @var string
     */
    public $class;

    /**
     * @var TypeHint[]
     */
    public $properties;

    /**
     * @var string
     */
    public $uniqueName;

    /**
     * ObjectInformation constructor.
     * @param string $class
     * @param TypeHint[] $properties
     * @internal param string $uniqueName
     */
    public function __construct($class, array $properties)
    {
        $this->class = $class;
        $this->uniqueName = $class;
        $this->properties = $properties;
    }
}