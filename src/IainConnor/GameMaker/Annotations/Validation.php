<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Whitelist
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("PROPERTY")
 */
class Validation
{
    /**
     * @Required()
     * @var string[]
     */
    public $rules;
}