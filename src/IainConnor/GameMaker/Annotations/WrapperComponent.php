<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class WrapperComponent
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
class WrapperComponent
{
    /**
     * @var string
     */
    public $property;
}