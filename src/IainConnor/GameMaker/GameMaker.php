<?php


namespace IainConnor\GameMaker;


use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use IainConnor\GameMaker\Annotations\API;
use IainConnor\GameMaker\Annotations\Controller;
use IainConnor\GameMaker\Annotations\DELETE;
use IainConnor\GameMaker\Annotations\HEAD;
use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\IgnoreHttpMethod;
use IainConnor\GameMaker\Annotations\PATCH;
use IainConnor\GameMaker\Annotations\POST;
use IainConnor\GameMaker\Annotations\PUT;

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
	 * Boot and ensure requirements are filled.
	 */
	protected static function boot() {

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

		foreach ($reflectedClass->getMethods() as $reflectionMethod) {
			if ( static::$annotationReader->getMethodAnnotation($reflectionMethod, IgnoreHttpMethod::class) === null ) {
				$httpMethod = static::getFullHttpMethod($reflectionMethod, $apiAnnotation, $controllerAnnotation);
				if ( $httpMethod !== null ) {
					foreach (static::$annotationReader->getMethodAnnotations($reflectionMethod) as $methodAnnotation) {
						var_dump ( $httpMethod );
					}
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
			$definedMethod = static::guessHttpMethodFromMethodName($reflectionMethod->getShortName());
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
	 * @param $methodName
	 * @return HttpMethod|null
	 */
	protected static function guessHttpMethodFromMethodName($methodName) {
		$guessMap = [
			GET::class,
			POST::class,
			PUT::class,
			PATCH::class,
			DELETE::class,
			HEAD::class
		];

		foreach ( $guessMap as $guess ) {
			if ( substr(strtolower($methodName), 0, strlen($guess)) == strtolower($guess) ) {

				/** @var HttpMethod $httpMethod */
				$httpMethod = new $guess();
				$httpMethod->path = strtolower(preg_replace("/([A-Z_])/", "/$1", substr($methodName, strlen($guess))));

				return $httpMethod;
			}
		}

		return null;
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
}