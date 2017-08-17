<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Validation
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
class Validation
{
    /**
     * @Required()
     * @var string[]|string
     */
    public $rules;
}