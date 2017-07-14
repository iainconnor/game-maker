<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Middleware
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Middleware
{
    /**
     * @Required()
     * @var string[]|string
     */
    public $names;
}