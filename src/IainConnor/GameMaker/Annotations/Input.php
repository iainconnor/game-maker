<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Annotates information about an input to the method.
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
class Input
{
    /** @var string The name of the input. */
    public $name;

    /** @var string The variable name to associate to in the associated Controller method. */
    public $variableName;

    /**
     * @var string The location of the input in the request.
     * @Enum({"PATH", "QUERY", "FORM", "BODY", "HEADER"})
     */
    public $in;

    /** @var array A list of valid values for this input. */
    public $enum;

    /** The type information associated with this input. */
    public $typeHint;

    /** @var string[] @TODO Iain needs improving */
    public $validationRules;

    /**
     * @var string The format of the array data.
     * @Enum({"CSV", "SSV", "TSV", "PIPES", "MULTI", "BRACKETS"})
     */
    public $arrayFormat;

    /** @var bool Omits this input from documentation output if true. */
    public $skipDoc = false;
}