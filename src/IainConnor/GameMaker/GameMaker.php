<?php


namespace IainConnor\GameMaker;


use hanneskod\classtools\Iterator\ClassIterator;
use IainConnor\Cornucopia\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use IainConnor\Cornucopia\Annotations\InputTypeHint;
use IainConnor\Cornucopia\Annotations\OutputTypeHint;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use IainConnor\GameMaker\Annotations\API;
use IainConnor\GameMaker\Annotations\Controller;
use IainConnor\GameMaker\Annotations\DELETE;
use IainConnor\GameMaker\Annotations\GET;
use IainConnor\GameMaker\Annotations\HEAD;
use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\IgnoreEndpoint;
use IainConnor\GameMaker\Annotations\IgnoreOutputWrapper;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\Output;
use IainConnor\GameMaker\Annotations\OutputWrapper;
use IainConnor\GameMaker\Annotations\PATCH;
use IainConnor\GameMaker\Annotations\POST;
use IainConnor\GameMaker\Annotations\PUT;
use IainConnor\GameMaker\NamingConventions\CamelToSnake;
use IainConnor\GameMaker\NamingConventions\NamingConvention;
use IainConnor\GameMaker\Utils\HttpStatusCodes;
use Symfony\Component\Finder\Finder;

class GameMaker {

    /**
     * @var GameMaker
     */
    protected static $instance;

	/**
	 * Doctrine Annotation reader.
	 * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
	 * @var CachedReader
	 */
	protected $annotationReader;

	/**
	 * Naming convention to use when guessing the path for a function name.
	 * @var NamingConvention
	 */
	protected $functionToPathNamingConvention;

	/**
	 * Naming convention to use when guessing the input name for a given variable name.
	 * @var NamingConvention
	 */
	protected $variableNameToInputNamingConvention;

	/** @var int Maximum depth to recurse in parsed Objects */
	protected $maxRecursionDepth = 3;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * GameMaker constructor.
     * @param CachedReader $annotationReader
     * @param NamingConvention $functionToPathNamingConvention
     * @param NamingConvention $variableNameToInputNamingConvention
     * @param Finder $finder
     */
    public function __construct(CachedReader $annotationReader, NamingConvention $functionToPathNamingConvention, NamingConvention $variableNameToInputNamingConvention, Finder $finder)
    {
        $this->annotationReader = $annotationReader;
        $this->functionToPathNamingConvention = $functionToPathNamingConvention;
        $this->variableNameToInputNamingConvention = $variableNameToInputNamingConvention;
        $this->finder = $finder;
    }

    /**
     * Retrieve or boot an instance.
     * @return GameMaker
     */
    public static function instance() {
        if ( static::$instance == null ) {
            static::$instance = static::boot();
        }

        return static::$instance;
    }

    /**
	 * Boot and ensure requirements are filled.
	 */
	protected static function boot() {

        AnnotationRegistry::registerAutoloadNamespace('\IainConnor\GameMaker\Annotations', static::getSrcRoot());

	    return new GameMaker(
            new CachedReader(
                new AnnotationReader(),
                new ArrayCache(),
                false
            ),
            new CamelToSnake(),
            new CamelToSnake(),
            new Finder()
        );
	}

    /**
     * Retrieve all endpoints and objects defined in the specified path.
     *
     * @param $path
     * @param int $depth
     * @return ControllerInformation[]
     */
	public function parseControllersInPath($path, $depth = 0) {

	    return $this->parseControllersInNamespaceInPath(null, $path, $depth);
    }

    /**
     * Retrieve all endpoints and objects defined in the specified namespace.
     *
     * @param $namespace
     * @param $path
     * @param int $depth
     * @return ControllerInformation[]
     */
	public function parseControllersInNamespaceInPath($namespace, $path, $depth = 0) {
        $classes = [];

        $this->finder->sortByName();
        $this->finder->in($path)->depth($depth)->name("*.php");

        $iterator = new ClassIterator($this->finder);

        /** @var \ReflectionClass $reflectionClass */
        foreach ( $iterator->inNamespace($namespace) as $reflectionClass ) {
            $classes[] = $reflectionClass->getName();
        }

        return $this->parseControllers($classes);
    }

    /**
     * Retrieve all endpoints and objects defined in the specified classes.
     *
     * @param array $classes
     * @return ControllerInformation[]
     */
    public function parseControllers(array $classes) {
        return array_map([$this, "parseController"], $classes);
    }

    /**
     * Retrieve all endpoints and objects defined in the specified class.
     *
     * @param $class
     * @return ControllerInformation
     */
	public function parseController($class) {

		/** @var Endpoint[] $endpoints */
		$endpoints = [];

		/** @var ObjectInformation[] $parsedObjects */
		$parsedObjects = [];

		$reflectedClass = new \ReflectionClass($class);

		/** @var API|null $apiAnnotation */
		$apiAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, API::class);

		/** @var ControllerInformation|null $controllerAnnotation */
		$controllerAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, Controller::class);

		/** @var OutputWrapper|null $outputWrapperAnnotation */
		$outputWrapperAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, OutputWrapper::class);

		foreach ($reflectedClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
			$endpoint = new Endpoint();
			if ($this->annotationReader->getMethodAnnotation($reflectionMethod, IgnoreEndpoint::class) === null) {
				$httpMethod = $this->getFullHttpMethod($reflectionMethod, $apiAnnotation, $controllerAnnotation);

				if ($httpMethod !== null) {
					$endpoint->httpMethod = $httpMethod;
					$endpoint->inputs = $this->getInputsForMethod($reflectionMethod, $httpMethod);
                    $endpoint->outputs = $this->getOutputsForMethod($reflectionMethod, $httpMethod, $outputWrapperAnnotation, $parsedObjects);

					foreach ( array_map("trim", explode("\n", preg_replace("/^(\s*)(\/*)(\**)(\/*)/m", "", $reflectionMethod->getDocComment()))) as $docblockLine ) {
						if ( $docblockLine && substr($docblockLine, 0, 1) != "@" ) {
							$endpoint->description .= $docblockLine . "\n";
						}
					}
					if ( $endpoint->description ) {
						$endpoint->description = substr($endpoint->description, 0, strlen($endpoint->description) - 1);
					}


					$endpoints[] = $endpoint;
				}
			}
		}

		return new ControllerInformation($class, $endpoints, array_values($parsedObjects));
	}

	/**
	 * Retrieves the full HTTP method being described by the given method.
	 *
	 * @param \ReflectionMethod $reflectionMethod
	 * @param API|null $apiAnnotation
	 * @param Controller|null $controllerAnnotation
	 * @return HttpMethod|null
	 */
	protected function getFullHttpMethod(\ReflectionMethod $reflectionMethod, API $apiAnnotation = null, Controller $controllerAnnotation = null) {
		/** @var HttpMethod|null $definedMethod */
		$definedMethod = $this->annotationReader->getMethodAnnotation($reflectionMethod, HttpMethod::class);

		if ( $definedMethod === null ) {
			$definedMethod = $this->guessHttpMethodFromMethod($reflectionMethod);
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
	protected function guessHttpMethodFromMethod(\ReflectionMethod $method) {

		$methodName = $method->getShortName();

		foreach ( HttpMethod::$allHttpMethods as $guess ) {
			$guessFriendlyName = GameMaker::getAfterLastSlash($guess);

			if ( substr(strtolower($methodName), 0, strlen($guessFriendlyName)) == strtolower($guessFriendlyName) ) {

				/** @var HttpMethod $httpMethod */
				$httpMethod = new $guess();

				$methodLessHttpMethod = substr($methodName, strlen($guessFriendlyName));
                $methodLessHttpMethod = $this->escapeInputsInMethod($methodLessHttpMethod, $method);
				$methodLessHttpMethod = lcfirst($methodLessHttpMethod);

				$httpMethod->path = "/" . $this->functionToPathNamingConvention->convert($methodLessHttpMethod);

				return $httpMethod;
			}
		}

		return null;
	}

    /**
     * Returns a version of $methodName with all occurrences of its inputs escaped as /{Input}/.
     * Used for guessing path names based on method names.
     *
     * @param $methodName
     * @param \ReflectionMethod $method
     * @return string
     */
	protected function escapeInputsInMethod($methodName, \ReflectionMethod $method) {
        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

        foreach ($methodAnnotations as $key => $methodAnnotation) {

            if ( $methodAnnotation instanceof Input && $methodAnnotation->typeHint != null ) {
                $typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $methodAnnotation->typeHint, $this->annotationReader->getMethodImports($method));

                if ( $typeHint != null ) {
                    $methodName = $this->escapeInputInMethodName($typeHint->variableName, $methodName);
                }
            } else if ( $methodAnnotation instanceof InputTypeHint ) {
                $methodName = $this->escapeInputInMethodName($methodAnnotation->variableName, $methodName);
            }
        }

        $methodParameters = $method->getParameters();

        foreach ($methodParameters as $methodParameter) {

            $methodName = $this->escapeInputInMethodName($methodParameter->getName(), $methodName);
        }


        return $methodName;
    }

    /**
     * Returns a version of $methodName with all occurrences of $input escaped as /{Input}/.
     * Used for guessing path names based on method names.
     *
     * @param string $input
     * @param string $methodName
     * @return string
     */
    protected function escapeInputInMethodName($input, $methodName) {

        return trim(preg_replace("/(?<!{)(" . preg_quote(ucfirst($input), '/') . ")(?!})/", '/{$1}/', $methodName), '/');
    }

    /**
     * Retrieves the outputs for the given method.
     *
     * @param \ReflectionMethod $method
     * @param HttpMethod $httpMethod
     * @param OutputWrapper $outputWrapperAnnotation
     * @param ObjectInformation[] $parsedObjects
     * @return Output[]
     */
    protected function getOutputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod, OutputWrapper $outputWrapperAnnotation = null, array &$parsedObjects = []) {
        /** @var Output[] $outputs */
        $outputs = [];

        /** @var Output $outputForWrapper */
        $outputForWrapper = null;

        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

        foreach ($methodAnnotations as $key => &$methodAnnotation) {

            $output = null;

            if ( $methodAnnotation instanceof Output ) {
                $output = $methodAnnotation;

                if ( is_string($output->typeHint) ) {
                    // Check if type hint is a string. If it is, process it.

                    $output->typeHint = TypeHint::parseToInstanceOf(OutputTypeHint::class, $output->typeHint, $this->annotationReader->getMethodImports($method));
                } else {
                    for ($i = $key + 1; $i <= key(array_slice($methodAnnotations, -1, 1, true)); $i++) {
                        // Check if the output annotation is followed by a type hint.
                        // If it is, merge them.
                        if ($methodAnnotations[$i] instanceof OutputTypeHint) {
                            $output->typeHint = $methodAnnotations[$i];
                            unset($methodAnnotations[$i]);
                            break;
                        } else if ($methodAnnotations[$i] instanceof Output) {
                            break;
                        }
                    }
                }
            } else if ( $methodAnnotation instanceof OutputTypeHint ) {
                $output = new Output();
                $output->typeHint = $methodAnnotation;
            }

            if ( $output != null ) {
                // Fill in the blanks with rational defaults.
                if ( $output->statusCode == null ) {
                    $output->statusCode = $output->typeHint == null || (count($output->typeHint->types) == 1 && $output->typeHint->types[0]->type == null) ? HttpStatusCodes::NO_CONTENT : $this->getDefaultHttpStatusCodeForHttpMethod($httpMethod);;
                }

                if ( $output->typeHint != null ) {
                    foreach ( $output->typeHint->types as $outputType ) {
                        $outputTypeObjectOfInterest = $outputType->genericType ?: $outputType->type;
                        $this->parseObject($outputTypeObjectOfInterest, $parsedObjects);
                    }
                }

                $outputs[] = $output;

                // Take either the first or the specified output for the wrapper.
                if ( $outputForWrapper == null || $output->outputWrapperProvider ) {
                    $outputForWrapper = $output;
                }
            }
        }

        // Get the specific output wrapper, if defined.
        $outputWrapperAnnotation = $this->annotationReader->getMethodAnnotation($method, OutputWrapper::class) ? : $outputWrapperAnnotation;

        // Swap with defined output wrapper, if appropriate.
        if ( $outputForWrapper != null && $outputWrapperAnnotation != null && $this->annotationReader->getMethodAnnotation($method, IgnoreOutputWrapper::class) == null ) {
            // Create a unique name for this type of output wrapper for the specified type.
            $outputWrapperUniqueNameForType = $outputWrapperAnnotation->class . "Of" . ucfirst($outputForWrapper->typeHint->types[0]->type) . ($outputForWrapper->typeHint->types[0]->genericType ? ucfirst($outputForWrapper->typeHint->types[0]->genericType) : "");

            if ( !array_key_exists($outputWrapperUniqueNameForType, $parsedObjects) ) {
                $this->parseObject($outputWrapperAnnotation->class, $parsedObjects);
                $outputWrapperParsedObject = $parsedObjects[$outputWrapperAnnotation->class];
                $outputWrapperParsedObject->uniqueName = $outputWrapperUniqueNameForType;
                foreach ( $outputWrapperParsedObject->properties as $key=>$property ) {
                    if ( $property->variableName == $outputWrapperAnnotation->property ) {
                        $outputWrapperParsedObject->properties[$key]->types = $outputForWrapper->typeHint->types;
                        break;
                    }
                }

                $parsedObjects[$outputWrapperUniqueNameForType] = $outputWrapperParsedObject;
                unset($parsedObjects[$outputWrapperAnnotation->class]);
            }

        }

        return $outputs;
    }

    /**
     * Parses the given object, adding it any other Classes found along the way to $parsedObjects.
     *
     * @param $class
     * @param array $parsedObjects
     * @param int $depth
     */
    protected function parseObject ( $class, array &$parsedObjects, $depth = 1 ) {
        if ( class_exists($class) && !array_key_exists($class, $parsedObjects)) {
            $parsedObject = new ObjectInformation($class, []);
            $reflectionClass = new \ReflectionClass($class);

            foreach ( $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectedProperty ) {
                foreach ($this->annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
                    if ($propertyAnnotation instanceof TypeHint) {
                       $parsedObject->properties[] = $propertyAnnotation;

                       // Recurse into types that represent objects and ensure they're in the graph.
                        if ( $depth <= $this->maxRecursionDepth ) {
                            foreach ($propertyAnnotation->types as $type) {
                                $classOfInterest = $type->genericType ?: $type->type;
                                if ( class_exists($classOfInterest) ) {
                                    // Recurse.
                                    $this->parseObject($classOfInterest, $parsedObjects, $depth + 1);
                                }
                            }
                        }
                    }
                }
            }

            $parsedObjects[$class] = $parsedObject;
        }
    }

	/**
     * Retrieves the inputs for the given method.
     *
	 * @param \ReflectionMethod $method
	 * @param HttpMethod $httpMethod
	 * @return Annotations\Input[]
	 */
	protected function getInputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod) {
		/** @var Input[] $inputs */
		$inputs = [];

		$methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

		foreach ($methodAnnotations as $key => &$methodAnnotation) {
			$input = null;

			if ( $methodAnnotation instanceof Input ) {
				$input = $methodAnnotation;

				if ( is_string($input->typeHint) ) {
					// Check if type hint is a string. If it is, process it.
					$input->typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $input->typeHint, $this->annotationReader->getMethodImports($method));
				} else {
                    for ($i = $key + 1; $i <= key(array_slice($methodAnnotations, -1, 1, true)); $i++) {
                        // Check if the input annotation is followed by a type hint.
                        // If it is, merge them.
                        if ($methodAnnotations[$i] instanceof InputTypeHint) {
                            $input->typeHint = $methodAnnotations[$i];
                            unset($methodAnnotations[$i]);
                            break;
                        } else if ($methodAnnotations[$i] instanceof Input) {
                            break;
                        }
                    }
                }
			} else if ( $methodAnnotation instanceof InputTypeHint ) {
				$input = new Input();
				$input->typeHint = $methodAnnotation;
			}

			if ( $input != null ) {
				// Fill in the blanks with rational defaults.

				if ($input->name == null) {
					$input->name = $this->variableNameToInputNamingConvention->convert($input->typeHint->variableName);
				}

				if ( $input->variableName == null ) {
					$input->variableName = $input->typeHint->variableName;
				}

				if ($input->in == null) {
					if ( $this->doesVariableExistInPath($input->name, $httpMethod) ) {
						$input->in = "PATH";
					} else {
						$input->in = $this->getDefaultLocationForHttpMethod($httpMethod);
					}
				}

				$inputs[] = $input;
			}
		}

		return $inputs;
	}

    /**
     * Retrieve the default HTTP status code for the given HTTP method through some opinionated but sane defaults.
     *
     * @param HttpMethod $httpMethod
     * @return int
     */
	protected function getDefaultHttpStatusCodeForHttpMethod (HttpMethod $httpMethod) {
        switch ($httpMethod) {
            case POST::class:
            case PUT::class:
            case PATCH::class:
                return HttpStatusCodes::ACCEPTED;
            case DELETE::class:
                return HttpStatusCodes::NO_CONTENT;
            case GET::class:
            case HEAD::class:
            default:
                return HttpStatusCodes::OK;
        }
    }

    /**
     * Retrieve the default input location for the given HTTP method through some opinionated but sane defaults.
     *
     * @param HttpMethod $httpMethod
     * @return string
     */
	protected function getDefaultLocationForHttpMethod (HttpMethod $httpMethod ) {
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
	public function setAnnotationReader($annotationReader) {

		$this->annotationReader = $annotationReader;
	}

	/**
	 * Set the naming convention to use when guessing the path for a function name.
	 *
	 * @param NamingConvention $functionToPathNamingConvention
	 */
	public function setFunctionToPathNamingConvention($functionToPathNamingConvention) {

		$this->functionToPathNamingConvention = $functionToPathNamingConvention;
	}

	/**
	 * Set the naming convention to use when guessing the input name for a given variable name.
	 *
	 * @param NamingConvention $variableNameToInputNamingConvention
	 */
	public function setVariableNameToInputNamingConvention($variableNameToInputNamingConvention) {

		$this->variableNameToInputNamingConvention = $variableNameToInputNamingConvention;
	}

    /**
     * @param int $maxRecursionDepth
     */
    public function setMaxRecursionDepth($maxRecursionDepth)
    {
        $this->maxRecursionDepth = $maxRecursionDepth;
    }

    protected function doesVariableExistInPath($variable, HttpMethod $httpMethod) {

        return strpos($httpMethod->path, "{" . $variable . "}") !== false;
    }

    public static function getAfterLastSlash($string) {

        return strpos($string, '\\') === false ? $string : substr($string, strrpos($string, '\\') + 1);
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