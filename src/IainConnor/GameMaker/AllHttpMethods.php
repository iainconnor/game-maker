<?php


namespace IainConnor\GameMaker;


use IainConnor\GameMaker\Annotations\DELETE;
use IainConnor\GameMaker\Annotations\GET;
use IainConnor\GameMaker\Annotations\HEAD;
use IainConnor\GameMaker\Annotations\PATCH;
use IainConnor\GameMaker\Annotations\POST;
use IainConnor\GameMaker\Annotations\PUT;

class AllHttpMethods
{
    /**
     * @return string[]
     */
    public static function get()
    {
        return [
            GET::class,
            POST::class,
            HEAD::class,
            DELETE::class,
            PUT::class,
            PATCH::class
        ];
    }
}