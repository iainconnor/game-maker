<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Input
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
class Input {

    /**
     * @var string
     */
	public $name;

    /** @var string */
	public $variableName;

	/**
	 * @Enum({"PATH", "QUERY", "FORM", "BODY", "HEADER"})
	 */
	public $in;

	/** @var array */
	public $enum;

	public $typeHint;

    /**
     * @var string
     * @Enum({"CSV", "SSV", "TSV", "PIPES", "MULTI"})
     */
    public $arrayFormat;
}