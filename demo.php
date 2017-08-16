<?php

use IainConnor\GameMaker\OutputWrapper;

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
class Foo
{

    /**
     * You can define the endpoint for a method.
     *
     * @\IainConnor\GameMaker\Annotations\GET("/lorem")
     */
    public function lorem()
    {

    }

    /**
     * You can optionally ignore merging this path with the parent.
     *
     * @\IainConnor\GameMaker\Annotations\POST(path="http://www.mydemo.com/rest_api/ipsum", ignoreParent=true)
     */
    public function ipsum()
    {

    }

    /**
     * You can skip the HTTP method annotation, so long as the method name follows a naming convention of `{http-method-name}ApiPathName`.
     */
    public function deleteDolor()
    {

    }

    /**
     * But you can ignore public functions as well.
     *
     * @\IainConnor\GameMaker\Annotations\IgnoreEndpoint()
     */
    public function imPublic()
    {

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
     * For array types, you can customize how the multiple values are input.
     * @\IainConnor\GameMaker\Annotations\Input(name="custom_name", arrayFormat="CSV")
     * @param string[] $bar An array of strings.
     *
     * Inputs can be type-hinted as one of a set of possible values.
     * Inputs are required unless defaulted or type-hinted as null.
     * You can also omit inputs from documentation if you wish to hide them for whatever reason.
     * @\IainConnor\GameMaker\Annotations\Input(enum={"yes", "no"}, skipDoc=true)
     * @param null|string $baz An optional stringey boolean.
     */
    public function sit($foo, array $bar, $baz = null)
    {

    }

    /**
     * Inputs can be part of the query path.
     * They can also be validated by one or more rules.
     *
     * @\IainConnor\GameMaker\Annotations\POST(path="/amit/{foo}")
     * @\IainConnor\GameMaker\Annotations\Validation({"notEmpty", "shortString"})
     * @\IainConnor\GameMaker\Annotations\Validation({"shortString", "alphaString"})
     * @param string $foo A string.
     */
    public function amit($foo)
    {

    }

    /**
     * This works for guessed methods as well.
     *
     * You can tag methods with useful information.
     * @\IainConnor\GameMaker\Annotations\Tag(tags={"Foo", "Bar"})
     *
     * @\IainConnor\GameMaker\Annotations\Validation("notEmpty")
     * @param string $foo A string.
     */
    public function getConsecteturFoo($foo)
    {

    }

    /**
     * If, for whatever reason, you don't want to have (some or any) inputs in your method signature,
     * you can still document them, so long as you provide a type hint manually in the annotation.
     * You can also still bind validations to these, but the validation annotation must appear before the input.
     *
     * @\IainConnor\GameMaker\Annotations\GET(path="/adipiscing")
     *
     * @\IainConnor\GameMaker\Annotations\Validation("shortString")
     * @\IainConnor\GameMaker\Annotations\Input(typeHint="string $foo A string.")
     */
    public function adipiscing()
    {

    }

    /**
     * You can define output from the route like any other return value.
     *
     * @return Biz Just a demo Object.
     */
    public function getNullam()
    {

    }

    /**
     * Works for the Object graph.
     *
     * @return BizzExtended Just a demo Object.
     */
    public function getNullamExtended()
    {

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
     * @\IainConnor\GameMaker\Annotations\Output(statusCode=200, typeHint="int[] Just a demo int.")
     */
    public function deleteLibero()
    {

    }

    /**
     * It's fairly common that your API will use a wrapper for some standard output format, and just fill in specific
     * gaps in that format.
     *
     * Define the wrapper class and the property to override by default.
     * @\IainConnor\GameMaker\Annotations\OutputWrapper(class="Bar")
     *
     * And then return one or more properties to fill in spots in that wrapper.
     * @\IainConnor\GameMaker\Annotations\Output(outputWrapperPath="Response.foo")
     * @return string[] The fizz node in the wrapper.
     *
     * @return null Woah, no output.
     */
    public function getBibendum()
    {

    }

    /**
     * Non-public functions are ignored by default.
     */
    private function imPrivate()
    {

    }
}

/**
 * Another demo class.
 *
 * You can define a wrapper used for all routes in a Class.
 * As before, just define the wrapper class and the property to override by default.
 * @\IainConnor\GameMaker\Annotations\OutputWrapper(class="Bar")
 *
 *
 * You can also Tag entire Controllers.
 * @\IainConnor\GameMaker\Annotations\Tag(tags={"Foo", "Bar"})
 *
 * @\IainConnor\GameMaker\Annotations\API(path=MY_API_PATH)
 * @\IainConnor\GameMaker\Annotations\Controller(path="/baz")
 */
class Baz
{

    /**
     * You can override the Controller-level Tag.
     * @\IainConnor\GameMaker\Annotations\Tag(tags={"Baz"}, ignoreParent=true)
     *
     * @\IainConnor\GameMaker\Annotations\Output(outputWrapperPath="Response.fizz.fuzz", outputWrapperMode="MERGE")
     * @return Biz The node for the wrapper.
     */
    public function putEuismod()
    {

    }

    /**
     * You can override the Controller-level Tag.
     * @\IainConnor\GameMaker\Annotations\Tag(tags={"Baz"}, ignoreParent=true)
     *
     * @\IainConnor\GameMaker\Annotations\Output(outputWrapperPath="Response.a.b.c.d")
     * @return Biz The node for the wrapper.
     */
    public function patchQuis()
    {

    }

    /**
     * You can ignore the OutputWrapper for certain endpoints.
     *
     * @\IainConnor\GameMaker\Annotations\IgnoreOutputWrapper()
     * @return string Just a string.
     */
    public function deletePosuere()
    {

    }
}

class Bar extends OutputWrapper
{
    /**
     * Return the output format.
     *
     * @return array
     */
    public function getFormat()
    {
        return [
            'Response' => [
                'foo' => 'string',
                'bar' => 'string',
                'fizz' => [
                    'fuzz' => [
                        'bazz' => 'string',
                        'bizz' => 'string'
                    ]
                ]
            ]
        ];
    }
}

class Biz
{
    /** @var int The id. */
    public $id;

    /** @var string|null The username. */
    public $userName;
}

class BizzExtended extends Biz
{
    /** @var string The password. */
    public $password;
}

$gameMaker = \IainConnor\GameMaker\GameMaker::instance();

// You should always set an AnnotationReader to improve performance.
// @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
$gameMaker->setAnnotationReader(
    new \IainConnor\Cornucopia\CachedReader(
        new \IainConnor\Cornucopia\AnnotationReader(),
        new \Doctrine\Common\Cache\ArrayCache()
    ));


// Parse controllers into a usable format.
$controllers = $gameMaker->parseControllers([Foo::class, Baz::class]);


// And then output that parsed content as Markdown.
$markdown = new \IainConnor\GameMaker\Processors\Markdown("Demo", "Just a demonstration.");
echo($markdown->processControllers($controllers));

echo PHP_EOL . "--------" . PHP_EOL;

// Or a Swagger spec.
$swagger2 = new \IainConnor\GameMaker\Processors\Swagger2("Demo", new \IainConnor\GameMaker\NamingConventions\CamelToSnake(), "1.0", "Just a demonstration.");
echo($swagger2->processControllers($controllers));

echo PHP_EOL . "--------" . PHP_EOL;

// Or MySQL.
$mySqlSchema = new \IainConnor\GameMaker\Processors\MySqlSchema();
echo($mySqlSchema->processControllers($controllers));

echo PHP_EOL . "--------" . PHP_EOL;

// Or JSONSchema.
$jsonSchema = new \IainConnor\GameMaker\Processors\JsonSchema("Just a demonstration.");
echo(implode(PHP_EOL . PHP_EOL, $jsonSchema->processControllers($controllers)));

echo PHP_EOL . "--------" . PHP_EOL;

// Or whatever you want!