<?php


namespace IainConnor\GameMaker\Processors;


use IainConnor\Cornucopia\Type;
use IainConnor\GameMaker\ControllerInformation;
use IainConnor\GameMaker\GameMaker;
use IainConnor\GameMaker\ObjectInformation;
use IainConnor\GameMaker\Utils\HttpStatusCodes;
use IainConnor\GameMaker\Utils\MarkdownTableGenerator;

class Markdown extends Processor
{
    /** @var string */
    public $title;

    /** @var string|null */
    public $subTitle = null;

    /**
     * Markdown constructor.
     * @param string $title
     * @param null|string $subTitle
     */
    public function __construct($title, $subTitle = null)
    {
        $this->title = $title;
        $this->subTitle = $subTitle;
    }


    /**
     * @param array $controllers
     * @return string
     */
    public function processControllers(array $controllers)
    {
        $markdown = "# " . $this->title . PHP_EOL;
        if ($this->subTitle) {
            $markdown .= "### " . $this->subTitle . PHP_EOL;
        }
        $markdown .= PHP_EOL;

        $markdown .= "## Controllers." . PHP_EOL . PHP_EOL;
        $markdown .= $this->generateMarkdownForControllers($controllers);
        $markdown .= PHP_EOL;

        $markdown .= "## Entities." . PHP_EOL . PHP_EOL;
        $markdown .= $this->generateMarkdownForObjects(array_filter(GameMaker::getUniqueObjectInControllers($controllers), function (ObjectInformation $objectInformation) {
            return !isset($objectInformation->skipDoc) || $objectInformation->skipDoc !== true;
        }));
        $markdown .= PHP_EOL;

        if ($this->branding) {
            $markdown .= "Generated with " . json_decode('"\u2764"') . " by [GameMaker](https://github.com/iainconnor/game-maker)." . PHP_EOL;
        }

        return $markdown;
    }

    /**
     * @param ControllerInformation[] $controllers
     * @return string
     */
    protected function generateMarkdownForControllers(array $controllers)
    {
        $this->alphabetizeControllers($controllers);

        return join(PHP_EOL, array_map([$this, "generateMarkdownForController"], $controllers));
    }

    /**
     * @param ControllerInformation $controller
     * @return string
     */
    protected function generateMarkdownForController(ControllerInformation $controller)
    {
        $markdown = "### " . GameMaker::getAfterLastSlash($controller->class) . " Controller." . PHP_EOL;
        foreach ($controller->endpoints as $endpoint) {
            $markdown .= PHP_EOL . "#### `" . GameMaker::getAfterLastSlash(get_class($endpoint->httpMethod)) . "` [" . $endpoint->httpMethod->path . "](<" . $endpoint->httpMethod->path . ">)" . PHP_EOL;
            $markdown .= str_replace("\n", "  " . PHP_EOL, $endpoint->description) . PHP_EOL;

            if (count($endpoint->tags)) {
                $markdown .= PHP_EOL . "##### Tags" . PHP_EOL . PHP_EOL;

                foreach ($endpoint->tags as $tag) {
                    $markdown .= "* " . $tag . PHP_EOL;
                }
            }

            if (count($endpoint->inputs)) {
                $markdown .= PHP_EOL . "##### Inputs" . PHP_EOL . PHP_EOL;

                $markdownTableGenerator = new MarkdownTableGenerator(['Input Name', 'Variable Name', 'Located In', 'Description', 'Allowed Type(s)']);

                foreach ($endpoint->inputs as $input) {
                    if (!isset($input->skipDoc) || $input->skipDoc !== true) {
                        $markdownTableGenerator->addData([$input->name => [$input->name, '`$' . $input->variableName . '`', $input->in, $input->typeHint->description, join(', ', array_map(function (Type $type) {
                            return "`" . ($type->type ?: "NULL") . ($type->genericType ? (' <' . $type->genericType . '>') : "") . '`';
                        }, $input->typeHint->types))]]);
                    }
                }

                $markdown .= $markdownTableGenerator->render();
            }

            if (count($endpoint->outputs)) {
                $markdown .= PHP_EOL . "##### Outputs" . PHP_EOL . PHP_EOL;

                $markdownTableGenerator = new MarkdownTableGenerator(['Status', 'Status Code', 'Description', 'Type(s)']);

                foreach ($endpoint->outputs as $key => $output) {
                    if (!isset($output->skipDoc) || $output->skipDoc !== true) {
                        $markdownTableGenerator->addData([$key => [ucwords(strtolower(str_replace('_', ' ', HttpStatusCodes::getDescriptionForCode($output->statusCode)))) . '.', '`' . $output->statusCode . '`', $output->typeHint->description, join(', ', array_map(function (Type $type) {
                            return "`" . ($type->type ?: "NULL") . ($type->genericType ? (' <' . $type->genericType . '>') : "") . '`';
                        }, $output->typeHint->types))]]);
                    }
                }

                $markdown .= $markdownTableGenerator->render();
            }
        }

        return $markdown;
    }

    /**
     * @param ObjectInformation[] $objects
     * @return string
     */
    protected function generateMarkdownForObjects(array $objects)
    {
        $this->alphabetizeObjects($objects);

        return join(PHP_EOL, array_map([$this, "generateMarkdownForObject"], $objects));
    }

    /**
     * @param ObjectInformation $object
     * @return string
     */
    protected function generateMarkdownForObject(ObjectInformation $object)
    {
        $markdown = "### " . GameMaker::getAfterLastSlash($object->uniqueName) . " Entity." . PHP_EOL;
        $markdown .= "#### Class `" . $object->class . "`" . PHP_EOL . PHP_EOL;

        if (count($object->properties)) {
            $markdownTableGenerator = new MarkdownTableGenerator(['Property', 'Description', 'Type(s)']);

            foreach ($object->properties as $key => $property) {
                $markdownTableGenerator->addData([$key => ['`$' . $property->variableName . '`', $property->description, join(', ', array_map(function (Type $type) {
                    return "`" . ($type->type ?: "NULL") . ($type->genericType ? (' <' . $type->genericType . '>') : "") . '`';
                }, $property->types))]]);
            }

            $markdown .= $markdownTableGenerator->render();
        }

        return $markdown;
    }
}