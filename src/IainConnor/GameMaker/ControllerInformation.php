<?php


namespace IainConnor\GameMaker;


class ControllerInformation
{
    /** @var string */
    public $class;

    /**
     * @var Endpoint[]
     */
    public $endpoints;

    /**
     * @var ObjectInformation[]
     */
    public $parsedObjects;

    /**
     * Controller constructor.
     * @param string $class
     * @param Endpoint[] $endpoints
     * @param ObjectInformation[] $parsedObjects
     */
    public function __construct($class, array $endpoints, array $parsedObjects)
    {
        $this->class = $class;
        $this->endpoints = $endpoints;
        $this->parsedObjects = $parsedObjects;
    }


}