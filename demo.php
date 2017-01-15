<?php

include(dirname(__FILE__) . "/vendor/autoload.php");

const MY_DOMAIN = "http://www.mydemo.com";
const MY_API_PATH = MY_DOMAIN . "/rest_api";

/**
 * A demo class.
 *
 * You can define a root path for your API.
 * @\IainConnor\GameMaker\Annotations\API(path=MY_API_PATH)
 *
 * You can define a root path for a specific controller.
 * You can optionally ignore merging this with the parent annotation above.
 * @\IainConnor\GameMaker\Annotations\Controller(path="/foo", ignoreParent=false)
 */
class Foo {

	/**
	 * You can define the endpoint for a method.
	 *
	 * @\IainConnor\GameMaker\Annotations\GET(path="/lorem")
	 */
	public function lorem() {

	}

	/**
	 * You can optionally ignore merging this path with the parent.
	 *
	 * @\IainConnor\GameMaker\Annotations\POST(path="http://www.mydemo.com/rest_api_v2/ipsum", ignoreParent=true)
	 */
	public function ipsum() {

	}

	/**
	 * You can skip the HTTP method annotation, so long as the method name follows a naming convention of `{http-method-name}ApiPathName`.
	 */
	public function deleteDolor() {

	}

	/**
	 * Non-public functions are ignored by default.
	 */
	private function imPrivate() {

	}

	/**
	 * But you can ignore public functions as well.
	 *
	 * @\IainConnor\GameMaker\Annotations\IgnoreHttpMethod()
	 */
	public function imPublic() {

	}

	/**
	 * Methods can have inputs.
	 *
	 * @\IainConnor\GameMaker\Annotations\GET(path="/sit")
	 *
	 * By default, inputs are sourced from the most likely place given the HTTP method.
	 * For example, GET's come from query parameters, POST's come from post body, etc.
	 * This can be overridden.
	 * @\IainConnor\GameMaker\Annotations\Input(in="HEADER")
	 * @param string $foo A string.
	 *
	 * The names the input referred to as by the HTTP call can also be customized.
	 * @\IainConnor\GameMaker\Annotations\Input(name="custom_name")
	 * @param string[] $bar An array of strings.
	 *
	 * Inputs can be type-hinted as one of a set of possible values.
	 * Inputs are required unless defaulted or type-hinted as null.
	 * @\IainConnor\GameMaker\Annotations\Input(enum={"yes", "no"})
	 * @param null|string $baz An optional string boolean.
	 */
	public function sit($foo, array $bar, $baz = null) {

	}

	/**
	 * Inputs can be part of the query path.
	 *
	 * @\IainConnor\GameMaker\Annotations\POST(path="/amit/{foo}")
	 * @param string $foo A string.
	 */
	public function amit($foo) {

	}

	/**
	 * This works for guessed methods as well.
	 *
	 * @param string $foo A string.
	 */
	public function getConsecteturFoo($foo) {

	}

	/**
	 * If, for whatever reason, you don't want to have (some or any) inputs in your method signature,
	 * you can still document them, so long as you provide a type hint.
	 *
	 * @\IainConnor\GameMaker\Annotations\GET(path="/adipiscing")
	 *
	 * @\IainConnor\GameMaker\Annotations\Input(typeHint="string $foo A string.")
	 */
	public function adipiscing() {

	}
}

var_dump ( \IainConnor\GameMaker\GameMaker::getEndpointsForController(Foo::class) );