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
     * ObjectInformation constructor.
     * @param string $class
     * @param TypeHint[] $properties
     */
    public function __construct($class, array $properties)
    {
        $this->class = $class;
        $this->properties = $properties;
    }
}