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
 * @TODO Iain needs improvement
 */
class Validation
{
    /**
     * @Required()
     * @var string[]|string
     */
    public $rules;

    /** @var string */
    public $description;

    /** @var string */
    public $failMessage;

    /** @var string */
    public $failCode;

    /**
     * @var int HTTP Status code.
     * @Enum({100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207, 300, 301, 302, 303, 304, 305, 306, 307, 400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 422, 423, 424, 425, 426, 449, 450, 500, 501, 502, 503, 504, 505, 506, 507, 509, 510})
     */
    public $failStatusCode;
}