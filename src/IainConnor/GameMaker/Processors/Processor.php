<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\ObjectInformation;

abstract class Processor
{
    public function processController(ControllerInformation $controller)
    {

        return $this->processControllers([$controller]);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return mixed
     */
    public abstract function processControllers(array $controllers);

    /**
     * @param ObjectInformation[] $objects
     */
    protected function alphabetizeObjects(array &$objects)
    {
        usort($objects, function (ObjectInformation $a, ObjectInformation $b) {
            return $a->uniqueName >= $b->uniqueName ? 1 : -1;
        });
    }

    /**
     * @param ControllerInformation[] $controllers
     */
    protected function alphabetizeControllers(array &$controllers)
    {
        usort($controllers, function (ControllerInformation $a, ControllerInformation $b) {
            return $a->class >= $b->class ? 1 : -1;
        });
    }

    /**
     * @param array $strings
     * @param string $terminatorCharacter
     * @return null|string
     */
    protected function getLongestCommonPrefix(array $strings, $terminatorCharacter = '/')
    {
        $prefixLength = 0;
        for ($i = 0; $i < strlen($strings[0]); $i++) {
            $prefixChar = $strings[0][$i];

            for ($j = 1; $j < count($strings); $j++) {
                if (strlen($strings[$j]) - 1 < $i || $strings[$j][$i] !== $prefixChar) {
                    break(2);
                }
            }

            if (is_null($terminatorCharacter) || $terminatorCharacter === '' || $prefixChar == $terminatorCharacter) {
                $prefixLength = $i + strlen($terminatorCharacter);
            }
        }

        $longestPrefix = substr($strings[0], 0, $prefixLength);

        if (!$longestPrefix || $longestPrefix == "/") {

            return "";
        }

        return $longestPrefix;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function camelToSnake($string)
    {

        return ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $string)), '_');
    }
}