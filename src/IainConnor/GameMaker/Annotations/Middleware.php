<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Defines one or more middleware to run before this Controller or Endpoint.
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Middleware
{
    /**
     * @Required()
     * @var string[]|string The name or names of one or more middleware to run.
     */
    public $names;
}