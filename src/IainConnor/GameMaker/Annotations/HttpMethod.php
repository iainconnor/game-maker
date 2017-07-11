<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class HttpMethod
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
abstract class HttpMethod extends Path
{

    /** @var string[] */
    public static $allHttpMethods = [
        GET::class,
        POST::class,
        HEAD::class,
        DELETE::class,
        PUT::class,
        PATCH::class
    ];
}