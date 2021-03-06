<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Tag
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Tag
{
    /**
     * @Required()
     * @var string[]|string
     */
    public $tags;

    /** @var bool If set to true, skip merging the list of tags with the parent Controller or API */
    public $ignoreParent = false;
}