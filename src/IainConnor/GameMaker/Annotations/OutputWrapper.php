<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class OutputWrapper
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("CLASS")
 */
class OutputWrapper
{
    /**
     * @var string
     */
    public $class;

    /**
     * @var string
     */
    public $defaultProperty;
}