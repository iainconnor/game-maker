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
	 * you can still document them, so long as you provide a type hint manually in the annotation.
	 *
	 * @\IainConnor\GameMaker\Annotations\GET(path="/adipiscing")
	 *
	 * @\IainConnor\GameMaker\Annotations\Input(typeHint="string $foo A string.")
	 */
	public function adipiscing() {

	}

    /**
     * You can define output from the route like any other return value.
     *
     * @return string Just a demo string.
     */
	public function getNullam() {

    }

    /**
     * You can define multiple possible returns.
     *
     * And define the HTTP status code for each.
     * @\IainConnor\GameMaker\Annotations\Output(statusCode=202)
     * @return string Just a demo string.
     *
     * A sane default HTTP status code will be guessed if none is provided.
     * This is based on both the return type and the HTTP method.
     * @return null Woah, no output.
     *
     * If you don't want to use the `@return` tag, you can also manually specify the type hint.
     * @\IainConnor\GameMaker\Annotations\Output(statusCode=200, typeHint="int Just a demo int.")
     */
    public function deleteLibero() {

    }

    /**
     * It's fairly common that your API will use a wrapper for some standard output format, and just fill in specific
     * gaps in that format.
     *
     * Just return an instance of the wrapper.
     * @return Bar The wrapper.
     *
     * And fill in specific components in that wrapper.
     * @\IainConnor\GameMaker\Annotations\WrapperComponent(property="data")
     * @return string[] The data node in the wrapper.
     */
    public function getBibendum() {

    }
}

/**
 * Another demo class.
 *
 * You can define a wrapper used for all routes in a Class.
 * And define a default property to fill instead of using `WrapperComponent`.
 * @\IainConnor\GameMaker\Annotations\OutputWrapper(class="Bar", defaultProperty="data")
 */
class Baz {

    /**
     * @return int[] The data node for the wrapper.
     */
    public function putEuismod() {

    }
}

class Bar {
    /**
     * @var object[]
     */
    public $data;

    /**
     * @var string[]
     */
    public $errors = [];

    /**
     * @var string[]
     */
    public $messages = [];
}

$gameMaker = \IainConnor\GameMaker\GameMaker::instance();

// You should always set an AnnotationReader to improve performance.
// @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
$gameMaker->setAnnotationReader(
    new \IainConnor\Cornucopia\CachedReader(
        new \IainConnor\Cornucopia\AnnotationReader(),
        new \Doctrine\Common\Cache\ArrayCache()
    ));

var_dump ( $gameMaker->parseController(Foo::class) );
var_dump ( $gameMaker->parseController(Baz::class) );