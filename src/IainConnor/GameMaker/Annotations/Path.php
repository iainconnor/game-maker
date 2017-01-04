<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class Path
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 */
abstract class Path {

	/**
	 * @Required()
	 * @var string
	 */
	public $path;

	/** @var bool */
	public $ignoreParent = false;
}