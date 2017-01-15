<?php


namespace IainConnor\GameMaker;


use IainConnor\Cornucopia\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use IainConnor\GameMaker\Annotations\API;
use IainConnor\GameMaker\Annotations\Controller;
use IainConnor\GameMaker\Annotations\DELETE;
use IainConnor\GameMaker\Annotations\GET;
use IainConnor\GameMaker\Annotations\HEAD;
use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\IgnoreHttpMethod;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\PATCH;
use IainConnor\GameMaker\Annotations\POST;
use IainConnor\GameMaker\Annotations\PUT;
use IainConnor\GameMaker\NamingConventions\CamelToSnake;
use IainConnor\GameMaker\NamingConventions\NamingConvention;

class GameMaker {

	/**
	 * Has the instance been booted yet.
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Doctrine Annotation reader.
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @var CachedReader
	 */
	protected static $annotationReader;

	/**
	 * Naming convention to use when guessing the path for a function name.
	 * @var NamingConvention
	 */
	protected static $functionToPathNamingConvention;

	/**
	 * Naming convention to use when guessing the input name for a given variable name.
	 * @var NamingConvention
	 */
	protected static $variableNameToInputNamingConvention;

	/**
	 * Boot and ensure requirements are filled.
	 */
	protected static function boot() {

		if (static::$functionToPathNamingConvention == null) {
			static::$functionToPathNamingConvention = new CamelToSnake();
		}

		if (static::$variableNameToInputNamingConvention == null) {
			static::$variableNameToInputNamingConvention = new CamelToSnake();
		}

		if (!static::$booted) {
			AnnotationRegistry::registerAutoloadNamespace('\IainConnor\GameMaker\Annotations', static::getSrcRoot());
			static::$booted = true;
		}

		if (static::$annotationReader == null) {
			static::$annotationReader = new CachedReader(
				new AnnotationReader(),
				new ArrayCache(),
				false
			);
		}
	}


	public static function getEndpointsForController($class) {

		static::boot();

		/** @var Endpoint[] $endpoints */
		$endpoints = [];

		$reflectedClass = new \ReflectionClass($class);

		/** @var API|null $apiAnnotation */
		$apiAnnotation = static::$annotationReader->getClassAnnotation($reflectedClass, API::class);

		/** @var Controller|null $controllerAnnotation */
		$controllerAnnotation = static::$annotationReader->getClassAnnotation($reflectedClass, Controller::class);

		foreach ($reflectedClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
			$endpoint = new Endpoint();
			if (static::$annotationReader->getMethodAnnotation($reflectionMethod, IgnoreHttpMethod::class) === null) {
				$httpMethod = static::getFullHttpMethod($reflectionMethod, $apiAnnotation, $controllerAnnotation);

				if ($httpMethod !== null) {
					$endpoint->httpMethod = $httpMethod;
					$endpoint->inputs = static::getInputsForMethod($reflectionMethod, $httpMethod);

					$endpoints[] = $endpoint;
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Retrieves the full HTTP method being described by the given method.
	 *
	 * @param \ReflectionMethod $reflectionMethod
	 * @param API|null $apiAnnotation
	 * @param Controller|null $controllerAnnotation
	 * @return HttpMethod|null
	 */
	protected static function getFullHttpMethod(\ReflectionMethod $reflectionMethod, API $apiAnnotation = null, Controller $controllerAnnotation = null) {
		/** @var HttpMethod|null $definedMethod */
		$definedMethod = static::$annotationReader->getMethodAnnotation($reflectionMethod, HttpMethod::class);

		if ( $definedMethod === null ) {
			$definedMethod = static::guessHttpMethodFromMethod($reflectionMethod);
		}

		if ( $definedMethod !== null ) {
			if ($definedMethod->ignoreParent) {

				return $definedMethod;
			}

			if ( $controllerAnnotation !== null ) {

				$definedMethod->path = $controllerAnnotation->path . $definedMethod->path;

				if ( $controllerAnnotation->ignoreParent ) {

					return $definedMethod;
				}
			}

			if ( $apiAnnotation !== null ) {

				$definedMethod->path = $apiAnnotation->path . $definedMethod->path;
			}
		}

		return $definedMethod;
	}

	/**
	 * If possible, makes an intelligent guess at the type of HTTP method being described.
	 *
	 * @param \ReflectionMethod $method
	 * @return HttpMethod|null
	 */
	protected static function guessHttpMethodFromMethod(\ReflectionMethod $method) {

		$methodName = $method->getShortName();

		foreach ( HttpMethod::$allHttpMethods as $guess ) {
			$guessFriendlyName = static::getAfterLastSlash($guess);

			if ( substr(strtolower($methodName), 0, strlen($guessFriendlyName)) == strtolower($guessFriendlyName) ) {

				/** @var HttpMethod $httpMethod */
				$httpMethod = new $guess();
				$methodLessHttpMethod = lcfirst(substr($methodName, strlen($guessFriendlyName)));

				$httpMethod->path = "/" . static::$functionToPathNamingConvention->convert($methodLessHttpMethod);

				return $httpMethod;
			}
		}

		return null;
	}

	/**
	 * @param \ReflectionMethod $method
	 * @param HttpMethod $httpMethod
	 * @return Annotations\Input[]
	 */
	protected static function getInputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod) {
		/** @var Input[] $inputs */
		$inputs = [];

		$methodAnnotations = static::$annotationReader->getMethodAnnotations($method);

		foreach ($methodAnnotations as $key => &$methodAnnotation) {
			$input = null;

			if ( $methodAnnotation instanceof Input ) {
				$input = $methodAnnotation;

				if ( array_key_exists($key + 1, $methodAnnotations) && $methodAnnotations[$key + 1] instanceof  TypeHint ) {
					// Check if the input annotation is followed by a type hint.
					// If it is, merge them.

					$input->typeHint = $methodAnnotations[$key + 1];
					unset($methodAnnotations[$key + 1]);
				} else if ( is_string($input->typeHint) ) {
					// Check if type hint is a string. If it is, process it.

					$input->typeHint = TypeHint::parse($input->typeHint, static::$annotationReader->getMethodImports($method));
				}
			} else if ( $methodAnnotation instanceof TypeHint ) {
				$input = new Input();
				$input->typeHint = $methodAnnotation;
			}

			if ( $input != null ) {
				// Fill in the blanks with rational defaults.

				if ($input->name == null) {
					$input->name = static::$variableNameToInputNamingConvention->convert(substr($input->typeHint->variableName, 1));
				}

				if ( $input->variableName == null ) {
					$input->variableName = $input->typeHint->variableName;
				}

				if ($input->in == null) {
					if ( static::doesVariableExistInPath($input->name, $httpMethod) ) {
						$input->in = "PATH";
					} else {
						$input->in = static::getDefaultLocationForHttpMethod($httpMethod);
					}
				}

				$inputs[] = $input;
			}
		}

		return $inputs;
	}

	protected static function getDefaultLocationForHttpMethod (HttpMethod $httpMethod ) {
		switch ($httpMethod) {
			case POST::class:
			case PUT::class:
			case PATCH::class:
				return "FORM";
			case GET::class:
			case DELETE::class:
			case HEAD::class:
			default:
				return "QUERY";
		}
	}

	/**
	 * Set the AnnotationReader.
	 *
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @param CachedReader $annotationReader
	 */
	public static function setAnnotationReader($annotationReader) {

		self::$annotationReader = $annotationReader;
	}

	/**
	 * Set the naming convention to use when guessing the path for a function name.
	 *
	 * @param NamingConvention $functionToPathNamingConvention
	 */
	public static function setFunctionToPathNamingConvention($functionToPathNamingConvention) {

		self::$functionToPathNamingConvention = $functionToPathNamingConvention;
	}

	/**
	 * Set the naming convention to use when guessing the input name for a given variable name.
	 *
	 * @param NamingConvention $variableNameToInputNamingConvention
	 */
	public static function setVariableNameToInputNamingConvention($variableNameToInputNamingConvention) {

		self::$variableNameToInputNamingConvention = $variableNameToInputNamingConvention;
	}

	public static function getProjectRoot() {

		return static::getSrcRoot() . "/..";
	}

	public static function getSrcRoot() {

		$path = dirname(__FILE__);

		return $path . "/../..";
	}

	public static function getVendorRoot() {

		return static::getProjectRoot() . "/vendor";
	}

	protected static function getAfterLastSlash($string) {

		return substr($string, strrpos($string, '\\') + 1);
	}

	protected static function doesVariableExistInPath($variable, HttpMethod $httpMethod) {

		return strpos($httpMethod->path, "{" . $variable . "}") !== false;
	}
}