<?php

include(dirname(__FILE__) . "/vendor/autoload.php");

const MY_DOMAIN = "http://www.mydemo.com";
const MY_API_PATH = MY_DOMAIN . "/rest_api";
const MY_FOO_CONTROLLER_PATH = "/foo";

/**
 * Class Lorem
 *
 * @\IainConnor\GameMaker\Annotations\API(path=MY_API_PATH)
 * @\IainConnor\GameMaker\Annotations\Controller(path=MY_FOO_CONTROLLER_PATH)
 */
class Lorem {

	public function getIpsum() {

	}
}

\IainConnor\GameMaker\GameMaker::getEndpointsForController(Lorem::class);