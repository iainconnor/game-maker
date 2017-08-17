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
    protected $maxRecursionDepth = 5;

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

    /** @var string[] */
    protected $ignoredInputTypes = [];

    /** @var string[] */
    protected $ignoredOutputTypes = [];

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
     * Adds a type to ignore when documenting input.
     *
     * @param string $type
     */
    public function addIgnoredInputType($type)
    {
        if (array_search($type, $this->ignoredInputTypes) === false) {
            $this->ignoredInputTypes[] = $type;
        }
    }

    /**
     * Adds a type to ignore when documenting output.
     *
     * @param string $type
     */
    public function addIgnoredOutputType($type)
    {
        if (array_search($type, $this->ignoredOutputTypes) === false) {
            $this->ignoredOutputTypes[] = $type;
        }
    }

    /**
     * Sets an array of types to ignore when documenting input.
     *
     * @param string[] $types
     */
    public function setIgnoredInputTypes(array $types)
    {
        $this->ignoredInputTypes = $types;
    }

    /**
     * Sets an array of types to ignore when documenting output.
     *
     * @param string[] $types
     */
    public function setIgnoredOutputTypes(array $types)
    {
        $this->ignoredOutputTypes = $types;
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
     * @param string $fileNamePattern
     * @param string $classNamePattern
     * @return ControllerInformation[]
     */
    public function parseControllersInPath($path, $depth = 0, $fileNamePattern = '*.php', $classNamePattern = '')
    {

        return $this->parseControllersInNamespaceInPath(null, $path, $depth, $fileNamePattern, $classNamePattern);
    }

    /**
     * Retrieve all endpoints and objects defined in the specified namespace.
     *
     * @param $namespace
     * @param $path
     * @param int $depth
     * @param string $fileNamePattern
     * @param string $classNamePattern
     * @return ControllerInformation[]
     */
    public function parseControllersInNamespaceInPath($namespace, $path, $depth = 0, $fileNamePattern = '*.php', $classNamePattern = '')
    {
        $classes = [];

        $this->finder->sortByName();
        $this->finder->in($path)->name($fileNamePattern);
        if ($depth) {
            $this->finder->depth('< ' . $depth);
        }

        $iterator = new ClassIterator($this->finder);

        /** @var \ReflectionClass $reflectionClass */
        foreach ($iterator->inNamespace($namespace)->name('/' . $classNamePattern . '/') as $reflectionClass) {
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
                    $endpoint->inputs = $this->getInputsForMethod($reflectionMethod, $httpMethod, $parsedObjects);
                    $endpoint->outputs = $this->getOutputsForMethod($reflectionMethod, $httpMethod, $outputWrapperAnnotation, $parsedObjects);
                    $endpoint->tags = $this->getTagsForMethod($reflectionMethod, $tagAnnotation);

                    if (preg_match_all("/^[\t ]*\*[\t ]*(.+[^\/])$/m", $reflectionMethod->getDocComment(), $matches)) {
                        foreach ($matches[1] as $docblockLine) {
                            if ($docblockLine && substr($docblockLine, 0, 1) != "@") {
                                $endpoint->description .= $docblockLine . PHP_EOL;
                            }
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
            $httpMethod->path = getenv($definedMethod->path) !== false ? getenv($definedMethod->path) : $definedMethod->path;
            $httpMethod->ignoreParent = $definedMethod->ignoreParent;
            $httpMethod->friendlyName = $definedMethod->friendlyName;
        }

        if ($httpMethod !== null) {
            if ($httpMethod->ignoreParent) {

                return $httpMethod;
            }

            if ($controllerAnnotation !== null) {

                $httpMethod->path = (getenv($controllerAnnotation->path) !== false ? getenv($controllerAnnotation->path) : $controllerAnnotation->path) . $httpMethod->path;

                if ($controllerAnnotation->ignoreParent) {

                    return $httpMethod;
                }
            }

            if ($apiAnnotation !== null) {

                $httpMethod->path = (getenv($apiAnnotation->path) !== false ? getenv($apiAnnotation->path) : $apiAnnotation->path) . $httpMethod->path;
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
     * @param ObjectInformation[] $parsedObjects
     * @return Input[]
     * @throws \Exception
     */
    protected function getInputsForMethod(\ReflectionMethod $method, HttpMethod $httpMethod, array &$parsedObjects = [])
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

                if ($input->typeHint != null) {
                    foreach ($input->typeHint->types as $type) {
                        if ($input->skipDoc !== true && (array_search($type->type, $this->ignoredInputTypes) !== false || array_search($type->genericType, $this->ignoredInputTypes) !== false)) {
                            $input->skipDoc = true;
                        }

                        $this->parseObject($type->getTypeOfInterest(), $parsedObjects, isset($input->skipDoc) && $input->skipDoc === true);

                        if ($type->type == TypeHint::ARRAY_TYPE) {
                            if ($input->arrayFormat == null) {
                                $input->arrayFormat = $input->in == 'BODY' ? 'BRACKETS' : $this->defaultArrayFormat;
                            } else if ($input->arrayFormat == 'MULTI' && !($input->in == 'QUERY' || $input->in == 'FORM')) {
                                throw new \Exception("MULTI array format can only be used for inputs in the QUERY or FORM.");
                            }
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

        // Get the specific output wrapper, if defined, or the parent.
        $outputWrapperAnnotation = $this->annotationReader->getMethodAnnotation($method, OutputWrapper::class) ?: $outputWrapperAnnotation;
        if ($outputWrapperAnnotation && $this->annotationReader->getMethodAnnotation($method, IgnoreOutputWrapper::class) == null) {
            $outputWrapperClass = $outputWrapperAnnotation->class;
            /** @var \IainConnor\GameMaker\OutputWrapper $outputWrapper */
            $outputWrapper = new $outputWrapperClass;
            $outputWrapperFormat = $outputWrapper->getFormat();
            $outputWrapperUniqueNameParts = [];
            $outputWrapperStatusCode = HttpStatusCodes::OK;
        }

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
                        if ($output->skipDoc !== true && (array_search($outputType->type, $this->ignoredOutputTypes) !== false || array_search($outputType->genericType, $this->ignoredOutputTypes) !== false)) {
                            $output->skipDoc = true;
                        }

                        $this->parseObject($outputType->getTypeOfInterest(), $parsedObjects, isset($output->skipDoc) && $output->skipDoc === true);
                    }
                }

                if ($output->outputWrapperPath != null && $output->typeHint != null && isset($outputWrapperFormat)) {
                    $outputWrapperStatusCode = $output->statusCode;

                    $outputWrapperUniqueNameParts[$output->outputWrapperPath] = array_map(function (Type $type) {
                        return $type->type == TypeHint::ARRAY_TYPE ? ('Array' . ($type->genericType ? 'Of' . ucfirst(GameMaker::getAfterLastSlash($type->genericType)) . 's' : '')) : ucfirst(GameMaker::getAfterLastSlash($type->type)) ?: TypeHint::NULL_TYPE;
                    }, $output->typeHint->types);

                    // Alphabetize for consistency.
                    sort($outputWrapperUniqueNameParts[$output->outputWrapperPath]);

                    $this->addTypeInfoToOutputWrapperFormat($outputWrapperFormat, $output->outputWrapperPath, $output->outputWrapperMode, $output->typeHint->types, $parsedObjects);
                } else {
                    $outputs[] = $output;
                }
            }
        }

        // Swap output with defined output wrapper, if appropriate.
        if (!empty($outputWrapperFormat) && !empty($outputWrapperUniqueNameParts) && !empty($outputWrapperClass) && isset($outputWrapperStatusCode) && $outputWrapperAnnotation != null) {
            // Generate a unique name for this wrapper.
            $outputWrapperUniqueName = $outputWrapperClass . "With";
            $outputWrapperDescription = GameMaker::getAfterLastSlash($outputWrapperClass) . ' with ';
            foreach ($outputWrapperUniqueNameParts as $path => $typeList) {
                $outputWrapperUniqueName .= str_replace('.', '', ucwords($path, '.')) . 'As' . join('Or', $typeList) . 'And';

                $outputWrapperDescription .= '`' . $path . '` as ' . join(' or ', array_map(function ($element) {
                        return str_replace('ArrayOf', 'array of ', $element);
                    }, $typeList)) . ' and ';
            }

            $outputWrapperUniqueName = substr($outputWrapperUniqueName, 0, -3);

            // Need to build up an "Object" to represent this wrapper.
            $this->parseOutputWrapperFormat($outputWrapperUniqueName, $outputWrapperFormat, $parsedObjects, $this->annotationReader->getMethodImports($method));

            // And add the wrapper to our output list in place of the original output pieces.

            $outputWrapperTypeHint = new Type();
            $outputWrapperTypeHint->type = $outputWrapperUniqueName;

            $outputWrapperOutputTypeHint = new OutputTypeHint([$outputWrapperTypeHint], null, substr($outputWrapperDescription, 0, -5) . '.');

            $outputWrapperOutput = new Output();
            $outputWrapperOutput->statusCode = $outputWrapperStatusCode;
            $outputWrapperOutput->typeHint = $outputWrapperOutputTypeHint;


            $outputs[] = $outputWrapperOutput;
        }

        return $outputs;
    }

    /**
     * Retrieves a typehint for the given types.
     *
     * @param Type[] $types
     *
     * @return string
     */
    protected function getTypeHintStringForTypes(array $types)
    {
        if (empty($types)) {
            return TypeHint::NULL_TYPE;
        }

        return join(TypeHint::TYPE_SEPARATOR, array_map(function (Type $type) {
            return (string)$type;
        }, $types));
    }

    /**
     * Traverses the provided output wrapper and swaps the type hint at the given path for the given type.
     *
     * @param array $outputWrapperFormat
     * @param string $outputWrapperPath
     * @param string $outputWrapperMode
     * @param Type[] $types
     * @param ObjectInformation[] $parsedObjects
     */
    protected
    function addTypeInfoToOutputWrapperFormat(array &$outputWrapperFormat, $outputWrapperPath, $outputWrapperMode, array $types, array &$parsedObjects)
    {
        $outputWrapperPathParts = explode('.', $outputWrapperPath);
        $key = $outputWrapperPathParts[0];
        $atBottom = count($outputWrapperPathParts) == 1 || (array_key_exists($key, $outputWrapperFormat) && !is_array($outputWrapperFormat[$key]));
        $bottomDoesntExist = !array_key_exists($key, $outputWrapperFormat);
        if ($atBottom || $bottomDoesntExist) {
            // If we're at the bottom or we can't recurse anymore.
            if ($atBottom) {
                if ($outputWrapperMode == \IainConnor\GameMaker\OutputWrapper::MODE_OVERRIDE) {
                    $outputWrapperFormat[$key] = $this->getTypeHintStringForTypes($types);
                } else {
                    foreach ($types as $type) {
                        $parsedObject = $this->parseObject($type->getTypeOfInterest(), $parsedObjects);
                        if ($parsedObject) {
                            foreach ($parsedObject->properties as $property) {
                                $outputWrapperFormat[$key][$property->variableName] = $this->getTypeHintStringForTypes($property->types);
                            }
                        }
                    }
                }
            } else if ($bottomDoesntExist) {
                $this->setPathInArrayToValue($outputWrapperPath, $outputWrapperFormat, $this->getTypeHintStringForTypes($types));
            }
        } else {
            // Recurse.
            $this->addTypeInfoToOutputWrapperFormat($outputWrapperFormat[$key], join('.', array_slice($outputWrapperPathParts, 1)), $outputWrapperMode, $types, $parsedObjects);
        }
    }

    protected
    function setPathInArrayToValue($path, array &$array, $value)
    {
        $pathParts = explode('.', $path);
        $current = &$array;
        foreach ($pathParts as $pathPart) {
            $current = &$current[$pathPart];
        }
        $current = $value;
    }

    /**
     * Retrieve the default HTTP status code for the given HTTP method through some opinionated but sane defaults.
     *
     * @param HttpMethod $httpMethod
     * @return int
     */
    protected
    function getDefaultHttpStatusCodeForHttpMethod(HttpMethod $httpMethod)
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
     * Parses the given output wrapper format, adding it any other Classes found along the way to $parsedObjects.
     *
     * @param $class
     * @param array $outputWrapperFormat
     * @param array $parsedObjects
     * @param array $imports
     * @param int $depth
     * @return ObjectInformation|null
     */
    protected function parseOutputWrapperFormat($class, array $outputWrapperFormat, array &$parsedObjects, array $imports, $depth = 0)
    {
        if (array_key_exists($class, $parsedObjects)) {
            return $parsedObjects[$class];
        } else {
            $properties = [];

            foreach ($outputWrapperFormat as $key => $val) {
                if (is_array($val) && $depth <= $this->maxRecursionDepth) {
                    // Recurse into types that represent objects and ensure they're in the graph.
                    $outputWrapperPropertyClass = $class . ucfirst($key) . 'Property';
                    $this->parseOutputWrapperFormat($outputWrapperPropertyClass, $outputWrapperFormat[$key], $parsedObjects, $imports, $depth + 1);

                    $outputWrapperPropertyType = new Type();
                    $outputWrapperPropertyType->type = $outputWrapperPropertyClass;

                    $properties[] = new TypeHint([$outputWrapperPropertyType], $key, '');
                } else {
                    $properties[] = TypeHint::parseToInstanceOf(TypeHint::class, $val, $imports, '$' . $key);
                }
            }

            $outputWrapperObjectInformation = new ObjectInformation($class, $properties, $properties);
            $parsedObjects[$class] = $outputWrapperObjectInformation;

            return $outputWrapperObjectInformation;
        }
    }

    /**
     * Parses the given object, adding it any other Classes found along the way to $parsedObjects.
     *
     * @param $class
     * @param array $parsedObjects
     * @param bool $skipDoc
     * @param int $depth
     * @return ObjectInformation|null
     */
    protected function parseObject($class, array &$parsedObjects, $skipDoc = false, $depth = 0)
    {
        if (class_exists($class) && !array_key_exists($class, $parsedObjects)) {
            $parsedObject = new ObjectInformation($class, [], []);
            $reflectionClass = new \ReflectionClass($class);

            foreach ($reflectionClass->getProperties() as $reflectedProperty) {
                foreach ($this->annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
                    if ($propertyAnnotation instanceof TypeHint) {
                        $parsedObject->properties[] = $propertyAnnotation;
                        if ($reflectedProperty->getDeclaringClass() == $reflectionClass) {
                            $parsedObject->specificProperties[] = $propertyAnnotation;
                        }

                        // Recurse into types that represent objects and ensure they're in the graph.
                        if ($depth <= $this->maxRecursionDepth) {
                            foreach ($propertyAnnotation->types as $type) {
                                // Recurse.
                                $this->parseObject($type->getTypeOfInterest(), $parsedObjects, $skipDoc, $depth + 1);
                            }
                        }
                    }
                }
            }

            $parsedObject->skipDoc = $skipDoc;
            $parsedObjects[$class] = $parsedObject;

            return $parsedObject;
        }

        return array_key_exists($class, $parsedObjects) ? $parsedObjects[$class] : null;
    }

    /**
     * @param \ReflectionMethod $method
     * @param Tag|null $controllerLevelTag
     * @return string[]
     */
    protected
    function getTagsForMethod(\ReflectionMethod $method, Tag $controllerLevelTag = null)
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
    public
    function setAnnotationReader($annotationReader)
    {

        $this->annotationReader = $annotationReader;
    }

    /**
     * Set the naming convention to use when guessing the path for a function name.
     *
     * @param NamingConvention $functionToPathNamingConvention
     */
    public
    function setFunctionToPathNamingConvention($functionToPathNamingConvention)
    {

        $this->functionToPathNamingConvention = $functionToPathNamingConvention;
    }

    /**
     * Set the naming convention to use when guessing the input name for a given variable name.
     *
     * @param NamingConvention $variableNameToInputNamingConvention
     */
    public
    function setVariableNameToInputNamingConvention($variableNameToInputNamingConvention)
    {

        $this->variableNameToInputNamingConvention = $variableNameToInputNamingConvention;
    }

    /**
     * @return NamingConvention
     */
    public function getFunctionToPathNamingConvention()
    {
        return $this->functionToPathNamingConvention;
    }

    /**
     * @return NamingConvention
     */
    public function getVariableNameToInputNamingConvention()
    {
        return $this->variableNameToInputNamingConvention;
    }

    /**
     * @param int $maxRecursionDepth
     */
    public
    function setMaxRecursionDepth($maxRecursionDepth)
    {
        $this->maxRecursionDepth = $maxRecursionDepth;
    }

    /**
     * @param string $defaultArrayFormat
     */
    public
    function setDefaultArrayFormat($defaultArrayFormat)
    {
        $this->defaultArrayFormat = $defaultArrayFormat;
    }

    /**
     * @return ControllerInformation[]
     */
    public
    function getParsedControllers()
    {
        return $this->parsedControllers;
    }

    /**
     * Retrieves the actual class for the given type.
     *
     * @param $type
     * @return string
     */
    public
    function getActualClassForType($type)
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
    public
    function getUniqueObjects()
    {

        return GameMaker::getUniqueObjectInControllers($this->parsedControllers);
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return ObjectInformation[]
     */
    public
    static function getUniqueObjectInControllers(array $controllers)
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