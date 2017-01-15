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

	public $name;

	public $validator;

	/**
	 * @Enum({"QUERY", "FORM", "BODY", "HEADER"})
	 */
	public $in;

	public $enum;
}