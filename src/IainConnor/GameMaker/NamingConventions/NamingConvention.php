<?php


namespace IainConnor\GameMaker\NamingConventions;


interface NamingConvention
{

    /**
     * Convert the given input into its naming convention output.
     *
     * @param $input
     * @return mixed
     */
    public function convert($input);
}