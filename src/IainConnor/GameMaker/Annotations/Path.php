<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * Class Path
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 */
abstract class Path
{
    /**
     * @Required()
     * @var string The URI to associate with this endpoint.
     */
    public $path;

    /** @var string A user-friendly name for documentation purposes. */
    public $friendlyName = null;

    /** @var bool If set to true, skip merging the path with the parent Controller or API */
    public $ignoreParent = false;
}