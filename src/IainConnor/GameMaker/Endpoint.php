<?php


namespace IainConnor\GameMaker;


use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\Output;

class Endpoint {

	/** @var HttpMethod */
	public $httpMethod;

	/** @var Input[] */
	public $inputs;

	/** @var Output[] */
	public $outputs;

	/** @var string|null */
	public $description;
}