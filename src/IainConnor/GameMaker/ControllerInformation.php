<?php


namespace IainConnor\GameMaker;


use IainConnor\Cornucopia\Annotations\TypeHint;

class ControllerInformation
{
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
     * @param Endpoint[] $endpoints
     * @param ObjectInformation[] $parsedObjects
     */
    public function __construct(array $endpoints, array $parsedObjects)
    {
        $this->endpoints = $endpoints;
        $this->parsedObjects = $parsedObjects;
    }


}