<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\ObjectInformation;

abstract class Processor
{
    /**
     * @param ControllerInformation[] $controllers
     * @return mixed
     */
    public abstract function processControllers(array $controllers);

    /**
     * @param ControllerInformation[] $controllers
     * @return ObjectInformation[]
     */
    protected function getUniqueObjects(array $controllers) {
        /** @var ObjectInformation[] $objects */
        $objects = [];

        foreach ( $controllers as $controller ) {
            foreach ( $controller->parsedObjects as $object ) {
                $objects[$object->uniqueName] = $object;
            }
        }

        return array_values($objects);
    }

    /**
     * @param ObjectInformation[] $objects
     */
    protected function alphabetizeObjects(array &$objects) {
        usort($objects, function(ObjectInformation $a, ObjectInformation $b) {
            return $a->uniqueName >= $b->uniqueName ? 1 : -1;
        });
    }

    /**
     * @param ControllerInformation[] $controllers
     */
    protected function alphabetizeControllers(array &$controllers) {
        usort($controllers, function(ControllerInformation $a, ControllerInformation $b) {
            return $a->class >= $b->class ? 1 : -1;
        });
    }
}