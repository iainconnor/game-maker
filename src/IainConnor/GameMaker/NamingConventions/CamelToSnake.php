<?php


namespace IainConnor\GameMaker\NamingConventions;


class CamelToSnake implements NamingConvention
{
    protected $replacementCharacter;

    /**
     * CamelToSnake constructor.
     *
     * @param $replacementCharacter null|string The character to replace camel case changes with.
     */
    public function __construct($replacementCharacter = "_")
    {

        $this->replacementCharacter = $replacementCharacter;
    }


    public function convert($input)
    {

        return strtolower(preg_replace("/(?<!{)([A-Z])(?!})/", $this->replacementCharacter . "$1", $input));
    }

}