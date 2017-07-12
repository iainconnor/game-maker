<?php


namespace IainConnor\GameMaker;


class ControllerInformation
{
    /** @var string */
    public $class;

    /** @var string[] */
    public $middleware;

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
     * @param string[] $middleware
     * @param Endpoint[] $endpoints
     * @param ObjectInformation[] $parsedObjects
     */
    public function __construct($class, array $middleware, array $endpoints, array $parsedObjects)
    {
        $this->class = $class;
        $this->middleware = $middleware;
        $this->endpoints = $endpoints;
        $this->parsedObjects = $parsedObjects;
    }


}