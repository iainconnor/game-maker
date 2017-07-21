<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * If present, only those methods with a specific HttpMethod annotation will be processed.
 * Otherwise, guessing will resolve a method like `getFoo()` to `GET /foo`.
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("CLASS")
 */
class Whitelist
{
}