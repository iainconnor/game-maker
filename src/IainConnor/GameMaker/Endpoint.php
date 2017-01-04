<?php


namespace IainConnor\GameMaker;


use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\Output;

class Endpoint {

	/** @var HttpMethod */
	protected $httpMethod;

	/** @var Input[] */
	protected $inputs;

	/** @var Output[] */
	protected $outputs;
}