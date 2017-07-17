<?php


namespace IainConnor\GameMaker;


use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use hanneskod\classtools\Iterator\ClassIterator;
use IainConnor\Cornucopia\AnnotationReader;
use IainConnor\Cornucopia\Annotations\InputTypeHint;
use IainConnor\Cornucopia\Annotations\OutputTypeHint;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\CachedReader;
use IainConnor\Cornucopia\Type;
use IainConnor\GameMaker\Annotations\API;
use IainConnor\GameMaker\Annotations\Controller;
use IainConnor\GameMaker\Annotations\DELETE;
use IainConnor\GameMaker\Annotations\GET;
use IainConnor\GameMaker\Annotations\HEAD;
use IainConnor\GameMaker\Annotations\HttpMethod;
use IainConnor\GameMaker\Annotations\IgnoreEndpoint;
use IainConnor\GameMaker\Annotations\IgnoreOutputWrapper;
use IainConnor\GameMaker\Annotations\Input;
use IainConnor\GameMaker\Annotations\Middleware;
use IainConnor\GameMaker\Annotations\Output;
use IainConnor\GameMaker\Annotations\OutputWrapper;
use IainConnor\GameMaker\Annotations\PATCH;
use IainConnor\GameMaker\Annotations\POST;
use IainConnor\GameMaker\Annotations\PUT;
use IainConnor\GameMaker\Annotations\Tag;
use IainConnor\GameMaker\Annotations\Validation;
use IainConnor\GameMaker\Annotations\Whitelist;
use IainConnor\GameMaker\NamingConventions\CamelToSnake;
use IainConnor\GameMaker\NamingConventions\NamingConvention;
use IainConnor\GameMaker\Utils\HttpStatusCodes;
use Symfony\Component\Finder\Finder;

class GameMaker
{

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

    /** @var int Maximum depth to recurse in parsed Objects. */
    protected $maxRecursionDepth = 3;

    /** @var string The default format for arrays. */
    protected $defaultArrayFormat = "CSV";

    /**
     * @var ControllerInformation[]
     */
    protected $parsedControllers = [];

    /**
     * @var Finder
     */
    protected $finder;

    /**
     * GameMaker constructor.
     *
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
     *
     * @return GameMaker
     */
    public static function instance()
    {
        if (static::$instance == null) {
            static::$instance = static::boot();
        }

        return static::$instance;
    }

    /**
     * Boot and ensure requirements are filled.
     */
    protected static function boot()
    {
        AnnotationRegistry::registerAutoloadNamespace('IainConnor\GameMaker\Annotations', static::getSrcRoot());

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

    public static function getSrcRoot()
    {

        $path = dirname(__FILE__);

        return $path . "/../..";
    }

    public static function getVendorRoot()
    {

        return static::getProjectRoot() . "/vendor";
    }

    public static function getProjectRoot()
    {

        return static::getSrcRoot() . "/..";
    }

    /**
     * Retrieve all endpoints and objects defined in the specified path.
     *
     * @param $path
     * @param int $depth
     * @return ControllerInformation[]
     */
    public function parseControllersInPath($path, $depth = 0)
    {

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
    public function parseControllersInNamespaceInPath($namespace, $path, $depth = 0)
    {
        $classes = [];

        $this->finder->sortByName();
        $this->finder->in($path)->depth($depth)->name("*.php");

        $iterator = new ClassIterator($this->finder);

        /** @var \ReflectionClass $reflectionClass */
        foreach ($iterator->inNamespace($namespace) as $reflectionClass) {
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
    public function parseControllers(array $classes)
    {

        return array_map([$this, "parseController"], $classes);
    }

    /**
     * Retrieve all endpoints and objects defined in the specified class.
     *
     * @param $class
     * @return ControllerInformation
     */
    public function parseController($class)
    {

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

        /** @var Tag|null $tagAnnotation */
        $tagAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, Tag::class);


        /** @var Middleware|null $middlewareAnnotation */
        $middlewareAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, Middleware::class);

        $whitelistAnnotation = $this->annotationReader->getClassAnnotation($reflectedClass, Whitelist::class);

        foreach ($reflectedClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {

            if (!$this->isMagicMethod($reflectionMethod) && !$reflectionMethod->isInternal() && !$reflectionMethod->isConstructor() && $this->annotationReader->getMethodAnnotation($reflectionMethod, IgnoreEndpoint::class) === null) {
                $httpMethod = $this->getFullHttpMethod($reflectionMethod, $apiAnnotation, $controllerAnnotation, $whitelistAnnotation !== null);

                if ($httpMethod !== null) {
                    $endpoint = new Endpoint();

                    /** @var Middleware|null $endpointMiddlewareAnnotation */
                    $endpointMiddlewareAnnotation = $this->annotationReader->getMethodAnnotation($reflectionMethod, Middleware::class);

                    $endpoint->method = $reflectionMethod->getName();
                    $endpoint->middleware = is_null($endpointMiddlewareAnnotation) ? [] : (!is_array($endpointMiddlewareAnnotation->names) ? [$endpointMiddlewareAnnotation->names] : $endpointMiddlewareAnnotation->names);
                    $endpoint->httpMethod = $httpMethod;
                    $endpoint->inputs = $this->getInputsForMethod($reflectionMethod, $httpMethod);
                    $endpoint->outputs = $this->getOutputsForMethod($reflectionMethod, $httpMethod, $outputWrapperAnnotation, $parsedObjects);
                    $endpoint->tags = $this->getTagsForMethod($reflectionMethod, $tagAnnotation);

                    foreach (array_map("trim", explode("\n", preg_replace("/^(\s*)(\/*)(\**)(\/*)/m", "", $reflectionMethod->getDocComment()))) as $docblockLine) {
                        if ($docblockLine && substr($docblockLine, 0, 1) != "@") {
                            $endpoint->description .= $docblockLine . "\n";
                        }
                    }
                    if ($endpoint->description) {
                        $endpoint->description = substr($endpoint->description, 0, strlen($endpoint->description) - 1);
                    }


                    $endpoints[] = $endpoint;
                }
            }
        }

        $controller = new ControllerInformation($class, is_null($middlewareAnnotation) ? [] : (!is_array($middlewareAnnotation->names) ? [$middlewareAnnotation->names] : $middlewareAnnotation->names), $endpoints, array_values($parsedObjects));

        $this->parsedControllers[$class] = $controller;

        return $controller;
    }

    /**
     * Returns whether the provided method is magic.
     *
     * @param \ReflectionMethod $reflectionMethod
     * @return bool
     */
    protected function isMagicMethod(\ReflectionMethod $reflectionMethod)
    {
        return strpos($reflectionMethod->getName(), '__') === 0;
    }

    /**
     * Retrieves the full HTTP method being described by the given method.
     *
     * @param \ReflectionMethod $reflectionMethod
     * @param API|null $apiAnnotation
     * @param Controller|null $controllerAnnotation
     * @param bool $whitelistMode
     * @return HttpMethod|null
     */
    protected function getFullHttpMethod(\ReflectionMethod $reflectionMethod, API $apiAnnotation = null, Controller $controllerAnnotation = null, $whitelistMode = false)
    {
        /** @var HttpMethod|null $definedMethod */
        $definedMethod = $this->annotationReader->getMethodAnnotation($reflectionMethod, HttpMethod::class);

        /** @var HttpMethod|null $httpMethod */
        $httpMethod = null;

        if ($definedMethod === null && !$whitelistMode) {
            $httpMethod = $this->guessHttpMethodFromMethod($reflectionMethod);
        } else if ($definedMethod !== null) {
            $definedMethodClass = get_class($definedMethod);
            $httpMethod = new $definedMethodClass;
            $httpMethod->path = $definedMethod->path;
            $httpMethod->ignoreParent = $definedMethod->ignoreParent;
            $httpMethod->friendlyName = $definedMethod->friendlyName;
        }

        if ($httpMethod !== null) {
            if ($httpMethod->ignoreParent) {

                return $httpMethod;
            }

            if ($controllerAnnotation !== null) {

                $httpMethod->path = $controllerAnnotation->path . $httpMethod->path;

                if ($controllerAnnotation->ignoreParent) {

                    return $httpMethod;
                }
            }

            if ($apiAnnotation !== null) {

                $httpMethod->path = $apiAnnotation->path . $httpMethod->path;
            }
        }

        return $httpMethod;
    }

    /**
     * If possible, makes an intelligent guess at the type of HTTP method being described.
     *
     * @param \ReflectionMethod $method
     * @return HttpMethod|null
     */
    protected function guessHttpMethodFromMethod(\ReflectionMethod $method)
    {

        $methodName = $method->getShortName();

        foreach (AllHttpMethods::get() as $guess) {
            $guessFriendlyName = GameMaker::getAfterLastSlash($guess);

            if (substr(strtolower($methodName), 0, strlen($guessFriendlyName)) == strtolower($guessFriendlyName)) {

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

    public static function getAfterLastSlash($string)
    {

        return strpos($string, '\\') === false ? $string : substr($string, strrpos($string, '\\') + 1);
    }

    /**
     * Returns a version of $methodName with all occurrences of its inputs escaped as /{Input}/.
     * Used for guessing path names based on method names.
     *
     * @param $methodName
     * @param \ReflectionMethod $method
     * @return string
     */
    protected function escapeInputsInMethod($methodName, \ReflectionMethod $method)
    {
        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

        foreach ($methodAnnotations as $key => $methodAnnotation) {

            if ($methodAnnotation instanceof Input && $methodAnnotation->typeHint != null) {
                $typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $methodAnnotation->typeHint, $this->annotationReader->getMethodImports($method));

                if ($typeHint != null) {
                    $methodName = $this->escapeInputInMethodName($typeHint->variableName, $methodName);
                }
            } else if ($methodAnnotation instanceof InputTypeHint) {
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
     * Returns a version of $methodName with all occurrences of $input escaped as /{input}/.
     * Used for guessing path names based on method names.
     *
     * @param string $input
     * @param string $methodName
     * @return string
     */
    protected function escapeInputInMethodName($input, $methodName)
    {

        return trim(preg_replace_callback("/(?<!{)(" . preg_quote(ucfirst($input), '/') . ")(?!})/", function ($matches) {
            return '/{' . strtolower($matches[1]) . '}/';
        }, $methodName), '/');
    }

    /**
     * Retrieves the inputs for the given method.
     *
     * @param \ReflectionMethod $method
     * @param HttpMethod $httpMethod
     * @return Annotations\Input[]
     */
    protected function getInputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod)
    {
        /** @var Input[] $inputs */
        $inputs = [];

        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

        // Merge Validation rules.
        foreach ($methodAnnotations as $key => &$methodAnnotation) {
            if ($methodAnnotation instanceof Validation) {
                for ($i = $key + 1; $i <= key(array_slice($methodAnnotations, -1, 1, true)); $i++) {
                    // Check if the input annotation is followed by a type hint.
                    // If it is, merge them.
                    if ($methodAnnotations[$i] instanceof InputTypeHint || $methodAnnotations[$i] instanceof Input) {
                        $additionalRules = $methodAnnotation->rules ? (is_array($methodAnnotation->rules) ? $methodAnnotation->rules : [$methodAnnotation->rules]) : [];
                        if (isset($methodAnnotations[$i]->validationRules) && is_array($methodAnnotations[$i]->validationRules)) {
                            $methodAnnotations[$i]->validationRules = array_merge($methodAnnotations[$i]->validationRules, $additionalRules);
                        } else {
                            $methodAnnotations[$i]->validationRules = $additionalRules;
                        }
                        break;
                    }
                }
            }
        }

        // Extract inputs and merge with typehints.
        foreach ($methodAnnotations as $key => &$methodAnnotation) {
            $input = null;

            if ($methodAnnotation instanceof Input) {
                $input = $methodAnnotation;

                if (is_string($input->typeHint)) {
                    // Check if type hint is a string. If it is, process it.
                    $input->typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $input->typeHint, $this->annotationReader->getMethodImports($method));
                } else {
                    for ($i = $key + 1; $i <= key(array_slice($methodAnnotations, -1, 1, true)); $i++) {
                        // Check if the input annotation is followed by a type hint.
                        // If it is, merge them.
                        if ($methodAnnotations[$i] instanceof InputTypeHint) {
                            $input->typeHint = $methodAnnotations[$i];
                            if (isset($methodAnnotations[$i]->validationRules)) {
                                if (is_array($input->validationRules)) {
                                    $input->validationRules = array_merge($input->validationRules, $methodAnnotations[$i]->validationRules);
                                } else {
                                    $input->validationRules = $methodAnnotations[$i]->validationRules;
                                }
                            }
                            unset($methodAnnotations[$i]);
                            break;
                        } else if ($methodAnnotations[$i] instanceof Input) {
                            break;
                        }
                    }
                }
            } else if ($methodAnnotation instanceof InputTypeHint) {
                $input = new Input();
                $input->typeHint = $methodAnnotation;
                if (isset($methodAnnotation->validationRules)) {
                    $input->validationRules = $methodAnnotation->validationRules;
                }
            }

            if ($input != null) {
                $input->validationRules = $input->validationRules ? (is_array($input->validationRules) ? array_values(array_unique($input->validationRules)) : [$input->validationRules]) : [];
                unset($input->typeHint->validationRules);

                // Fill in the blanks with rational defaults.

                if ($input->name == null) {
                    $input->name = $this->variableNameToInputNamingConvention->convert($input->typeHint->variableName);
                }

                if ($input->variableName == null) {
                    $input->variableName = $input->typeHint->variableName;
                }

                if ($input->in == null) {
                    if ($this->doesVariableExistInPath($input->name, $httpMethod)) {
                        $input->in = "PATH";
                    } else {
                        $input->in = $this->getDefaultLocationForHttpMethod($httpMethod);
                    }
                }

                if ($input->arrayFormat == null) {
                    foreach ($input->typeHint->types as $type) {
                        if ($type == TypeHint::ARRAY_TYPE) {
                            $input->arrayFormat = $this->defaultArrayFormat;
                            break;
                        }
                    }
                }

                $inputs[] = $input;
            }
        }

        return $inputs;
    }

    protected function doesVariableExistInPath($variable, HttpMethod $httpMethod)
    {

        return strpos($httpMethod->path, "{" . $variable . "}") !== false;
    }

    /**
     * Retrieve the default input location for the given HTTP method through some opinionated but sane defaults.
     *
     * @param HttpMethod $httpMethod
     * @return string
     */
    protected function getDefaultLocationForHttpMethod(HttpMethod $httpMethod)
    {
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
     * Retrieves the outputs for the given method.
     *
     * @param \ReflectionMethod $method
     * @param HttpMethod $httpMethod
     * @param OutputWrapper $outputWrapperAnnotation
     * @param ObjectInformation[] $parsedObjects
     * @return Output[]
     */
    protected function getOutputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod, OutputWrapper $outputWrapperAnnotation = null, array &$parsedObjects = [])
    {
        /** @var Output[] $outputs */
        $outputs = [];

        /** @var Output $outputForWrapper */
        $outputForWrapper = null;

        $methodAnnotations = $this->annotationReader->getMethodAnnotations($method);

        foreach ($methodAnnotations as $key => &$methodAnnotation) {

            $output = null;

            if ($methodAnnotation instanceof Output) {
                $output = $methodAnnotation;

                if (is_string($output->typeHint)) {
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
            } else if ($methodAnnotation instanceof OutputTypeHint) {
                $output = new Output();
                $output->typeHint = $methodAnnotation;
            }

            if ($output != null) {
                // Fill in the blanks with rational defaults.
                if ($output->statusCode == null) {
                    $output->statusCode = $output->typeHint == null || (count($output->typeHint->types) == 1 && $output->typeHint->types[0]->type == null) ? HttpStatusCodes::NO_CONTENT : $this->getDefaultHttpStatusCodeForHttpMethod($httpMethod);;
                }

                if ($output->typeHint != null) {
                    foreach ($output->typeHint->types as $outputType) {
                        $outputTypeObjectOfInterest = $outputType->genericType ?: $outputType->type;
                        $this->parseObject($outputTypeObjectOfInterest, $parsedObjects);
                    }
                }

                $outputs[] = $output;

                // Take either the first or the specified output for the wrapper.
                if ($outputForWrapper == null || $output->outputWrapperProvider) {
                    $outputForWrapper = $output;
                }
            }
        }

        // Get the specific output wrapper, if defined.
        $outputWrapperAnnotation = $this->annotationReader->getMethodAnnotation($method, OutputWrapper::class) ?: $outputWrapperAnnotation;

        // Swap with defined output wrapper, if appropriate.
        if ($outputForWrapper != null && $outputWrapperAnnotation != null && $this->annotationReader->getMethodAnnotation($method, IgnoreOutputWrapper::class) == null) {
            // Create a unique name for this type of output wrapper for the specified type.
            $outputWrapperAnnotation->class = ltrim($outputWrapperAnnotation->class, '\\');
            $outputWrapperUniqueNameForType = $outputWrapperAnnotation->class . "With" . ucfirst($outputForWrapper->typeHint->types[0]->type) . ($outputForWrapper->typeHint->types[0]->genericType ? ("Of" . ucfirst($outputForWrapper->typeHint->types[0]->genericType) . "s") : "");

            if (!array_key_exists($outputWrapperUniqueNameForType, $parsedObjects)) {
                $this->parseObject($outputWrapperAnnotation->class, $parsedObjects);

                $genericOutputWrapperParsedObject = $parsedObjects[$outputWrapperAnnotation->class];

                $parsedObjects[$outputWrapperUniqueNameForType] = $this->cloneOutputWrapperAndSwapPropertyTypeHint($genericOutputWrapperParsedObject, $outputWrapperUniqueNameForType, $outputWrapperAnnotation->property, $outputForWrapper->typeHint);
                unset($parsedObjects[$outputWrapperAnnotation->class]);
            }

            // And swap with the original output.
            foreach ($outputs as $key => $output) {
                if ($output == $outputForWrapper) {
                    $wrappedOutputType = new Type();
                    $wrappedOutputType->type = $outputWrapperUniqueNameForType;

                    $outputs[$key]->typeHint = new OutputTypeHint([$wrappedOutputType], null, $output->typeHint->description);

                    break;
                }
            }

        }

        return $outputs;
    }

    /**
     * Retrieve the default HTTP status code for the given HTTP method through some opinionated but sane defaults.
     *
     * @param HttpMethod $httpMethod
     * @return int
     */
    protected function getDefaultHttpStatusCodeForHttpMethod(HttpMethod $httpMethod)
    {
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
     * Parses the given object, adding it any other Classes found along the way to $parsedObjects.
     *
     * @param $class
     * @param array $parsedObjects
     * @param int $depth
     */
    protected function parseObject($class, array &$parsedObjects, $depth = 1)
    {
        if (class_exists($class) && !array_key_exists($class, $parsedObjects)) {
            $parsedObject = new ObjectInformation($class, [], []);
            $reflectionClass = new \ReflectionClass($class);

            foreach ($reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC) as $reflectedProperty) {
                foreach ($this->annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
                    if ($propertyAnnotation instanceof TypeHint) {
                        $parsedObject->properties[] = $propertyAnnotation;
                        if ($reflectedProperty->getDeclaringClass() == $reflectionClass) {
                            $parsedObject->specificProperties[] = $propertyAnnotation;
                        }

                        // Recurse into types that represent objects and ensure they're in the graph.
                        if ($depth <= $this->maxRecursionDepth) {
                            foreach ($propertyAnnotation->types as $type) {
                                $classOfInterest = $type->genericType ?: $type->type;

                                // Recurse.
                                $this->parseObject($classOfInterest, $parsedObjects, $depth + 1);
                            }
                        }
                    }
                }
            }

            $parsedObjects[$class] = $parsedObject;
        }
    }

    /**
     * Clones the specified generic version of an Outout Wrapper, swapping the type hint for the specified property with
     * the specified value.
     *
     * @param ObjectInformation $genericOutputWrapper
     * @param $uniqueName
     * @param $propertyNameToSwap
     * @param OutputTypeHint $typeHint
     * @return ObjectInformation
     */
    protected function cloneOutputWrapperAndSwapPropertyTypeHint(ObjectInformation $genericOutputWrapper, $uniqueName, $propertyNameToSwap, OutputTypeHint $typeHint)
    {
        $clonedOutputWrapper = new ObjectInformation($genericOutputWrapper->class, [], []);
        $clonedOutputWrapper->uniqueName = $uniqueName;

        foreach ($genericOutputWrapper->properties as $key => $property) {
            $clonedOutputWrapper->properties[$key] = clone $property;

            if ($property->variableName == $propertyNameToSwap) {
                $clonedOutputWrapper->properties[$key]->types = $typeHint->types;
            }
        }

        foreach ($genericOutputWrapper->specificProperties as $key => $property) {
            $clonedOutputWrapper->specificProperties[$key] = clone $property;

            if ($property->variableName == $propertyNameToSwap) {
                $clonedOutputWrapper->properties[$key]->types = $typeHint->types;
            }
        }

        return $clonedOutputWrapper;
    }

    /**
     * @param \ReflectionMethod $method
     * @param Tag|null $controllerLevelTag
     * @return string[]
     */
    protected function getTagsForMethod(\ReflectionMethod $method, Tag $controllerLevelTag = null)
    {
        $tags = [];

        /** @var Tag $methodLevelTag */
        $methodLevelTag = $this->annotationReader->getMethodAnnotation($method, Tag::class);

        if ($controllerLevelTag != null && ($methodLevelTag == null || !$methodLevelTag->ignoreParent)) {
            $tags += is_array($controllerLevelTag->tags) ? $controllerLevelTag->tags : [$controllerLevelTag->tags];
        }

        if ($methodLevelTag) {
            $tags += is_array($methodLevelTag->tags) ? $methodLevelTag->tags : [$methodLevelTag->tags];
        }

        return $tags;
    }

    /**
     * Set the AnnotationReader.
     *
     * @see http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html
     * @param CachedReader $annotationReader
     */
    public function setAnnotationReader($annotationReader)
    {

        $this->annotationReader = $annotationReader;
    }

    /**
     * Set the naming convention to use when guessing the path for a function name.
     *
     * @param NamingConvention $functionToPathNamingConvention
     */
    public function setFunctionToPathNamingConvention($functionToPathNamingConvention)
    {

        $this->functionToPathNamingConvention = $functionToPathNamingConvention;
    }

    /**
     * Set the naming convention to use when guessing the input name for a given variable name.
     *
     * @param NamingConvention $variableNameToInputNamingConvention
     */
    public function setVariableNameToInputNamingConvention($variableNameToInputNamingConvention)
    {

        $this->variableNameToInputNamingConvention = $variableNameToInputNamingConvention;
    }

    /**
     * @param int $maxRecursionDepth
     */
    public function setMaxRecursionDepth($maxRecursionDepth)
    {
        $this->maxRecursionDepth = $maxRecursionDepth;
    }

    /**
     * @param string $defaultArrayFormat
     */
    public function setDefaultArrayFormat($defaultArrayFormat)
    {
        $this->defaultArrayFormat = $defaultArrayFormat;
    }

    /**
     * @return ControllerInformation[]
     */
    public function getParsedControllers()
    {
        return $this->parsedControllers;
    }

    /**
     * Retrieves the actual class for the given type.
     *
     * @param $type
     * @return string
     */
    public function getActualClassForType($type)
    {
        if (class_exists($type)) {

            return $type;
        }

        foreach ($this->getUniqueObjects() as $object) {
            if ($object->uniqueName == $type) {

                return $object->class;
            }
        }

        return $type;
    }

    /**
     * Retrieves the unique set of objects in the currently parsed controllers.
     *
     * @return ObjectInformation[]
     */
    public function getUniqueObjects()
    {

        return GameMaker::getUniqueObjectInControllers($this->parsedControllers);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return ObjectInformation[]
     */
    public static function getUniqueObjectInControllers(array $controllers)
    {
        /** @var ObjectInformation[] $objects */
        $objects = [];

        foreach ($controllers as $controller) {
            foreach ($controller->parsedObjects as $object) {
                $objects[$object->uniqueName] = $object;
            }
        }

        return array_values($objects);
    }
}