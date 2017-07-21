<?php


namespace IainConnor\GameMaker;


abstract class OutputWrapper
{
    const MODE_OVERRIDE = "OVERRIDE";
    const MODE_MERGE = "MERGE";

    /**
     * Return the output format.
     *
     * @return array
     */
    abstract public function getFormat();
}