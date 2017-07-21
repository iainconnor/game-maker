<?php


namespace IainConnor\GameMaker\Annotations;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Class Output
 *
 * @package IainConnor\GameMaker\Annotations
 * @Annotation
 * @Target("METHOD")
 */
class Output
{
    /**
     * @var int HTTP Status code.
     * @Enum({100, 101, 102, 200, 201, 202, 203, 204, 205, 206, 207, 300, 301, 302, 303, 304, 305, 306, 307, 400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418, 422, 423, 424, 425, 426, 449, 450, 500, 501, 502, 503, 504, 505, 506, 507, 509, 510})
     */
    public $statusCode;

    /** The type information associated with this output. */
    public $typeHint;

    /** @var string The path to where to where to insert */
    public $outputWrapperPath;

    /**
     * @var string If the output wrapper path has existing content, controls whether to override that content or merge with it.
     * @Enum({"OVERRIDE", "MERGE"})
     */
    public $outputWrapperMode = \IainConnor\GameMaker\OutputWrapper::MODE_OVERRIDE;

    /** @var bool Omits this input from documentation output if true. */
    public $skipDoc = false;
}