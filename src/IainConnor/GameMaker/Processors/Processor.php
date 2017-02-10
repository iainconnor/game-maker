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

    public function processController(ControllerInformation $controller) {
        return array_shift($this->processControllers([$controller]));
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

    /**
     * @param array $strings
     * @return string|null
     */
    protected function getLongestCommonPrefix(array $strings) {
        $prefixLength = 0;
        while ($prefixLength < strlen($strings[0])) {
            $prefixChar = $strings[0][$prefixLength];

            for ($i=1; $i < count($strings); $i++) {
                if ($strings[$i][$prefixLength] !== $prefixChar) {
                    break(2);
                }
            }

            $prefixLength++;
        }

        $longestPrefix = substr($strings[0], 0, $prefixLength);

        if ( !$longestPrefix || $longestPrefix == "/" ) {

            return "";
        }

        return $longestPrefix;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function camelToSnake($string) {

        return ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $string)), '_');
    }
}