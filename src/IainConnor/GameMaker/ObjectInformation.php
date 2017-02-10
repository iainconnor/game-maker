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
     * @var TypeHint[] All the properties in this class and its parents.
     */
    public $properties;

    /**
     * @var TypeHint[] The properties in this specific class (not a parent class).
     */
    public $specificProperties;

    /**
     * @var string
     */
    public $uniqueName;

    /**
     * ObjectInformation constructor.
     * @param string $class
     * @param TypeHint[] $properties
     * @param TypeHint[] $specificProperties
     * @internal param string $uniqueName
     */
    public function __construct($class, array $properties, array $specificProperties)
    {
        $this->class = ltrim($class, '\\');
        $this->uniqueName = ltrim($class, '\\');
        $this->properties = $properties;
        $this->specificProperties = $specificProperties;
    }
}