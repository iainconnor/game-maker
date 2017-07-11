<?php


namespace IainConnor\GameMaker\Processors;

use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\Type;
use IainConnor\GameMaker\GameMaker;
use IainConnor\GameMaker\ObjectInformation;

class MySqlSchema extends Processor
{

    protected $typeMap = [
        'string' => 'varchar(255)',
        'int' => 'int',
        'float' => 'double',
        'bool' => 'int(1)'
    ];

    /**
     * @param array $controllers
     * @return string
     */
    public function processControllers(array $controllers)
    {
        $mySql = [];

        $uniqueTopLevelObjects = $this->getTopLevelObjects(GameMaker::getUniqueObjectInControllers($controllers));
        $this->alphabetizeObjects($uniqueTopLevelObjects);

        $uniqueNames = array_map(function ($element) {
            return $element->uniqueName;
        }, $uniqueTopLevelObjects);

        $longestUniqueNamePrefix = $this->getLongestCommonPrefix($uniqueNames);

        foreach ($uniqueTopLevelObjects as $object) {
            $tableName = $this->camelToSnake(substr($object->uniqueName, strlen($longestUniqueNamePrefix)));
            $columns = [];
            foreach ($object->properties as $property) {
                $sqlType = null;
                /** @var Type $type */
                foreach ($property->types as $type) {
                    if ($type->type != null) {
                        if ($type->type == TypeHint::ARRAY_TYPE) {
                            $sqlType = 'int';
                            $joinTableName = $this->camelToSnake(substr($object->uniqueName, strlen($longestUniqueNamePrefix))) . "_" . $this->camelToSnake($property->variableName);

                            $otherTableName = null;
                            $otherTableType = null;
                            if ($type->genericType && class_exists($type->genericType)) {
                                if (substr($type->genericType, 0, strlen($longestUniqueNamePrefix)) == $longestUniqueNamePrefix) {
                                    $otherTableName = $this->camelToSnake(substr($type->genericType, strlen($longestUniqueNamePrefix)));
                                } else {
                                    $otherTableName = $this->camelToSnake($type->genericType);
                                }

                                $otherTableType = 'int';
                            } else if (array_key_exists($type->genericType, $this->typeMap)) {
                                $otherTableType = $this->typeMap[$type->genericType];
                            }

                            if ($otherTableType) {
                                $mySql[] = "CREATE TABLE `" . $joinTableName . "` (" . PHP_EOL . "\t`" . $tableName . "_id` int NOT NULL COMMENT 'references `" . $tableName . "`.`id`'" . "," . PHP_EOL . "\t`" . ($otherTableName ? $otherTableName . "_id" : $this->camelToSnake($property->variableName)) . "` " . $otherTableType . ($otherTableName ? " COMMENT 'references `" . $otherTableName . "`.`id`'" : "") . PHP_EOL . ");";
                            }

                            $property->description = "references `" . $joinTableName . "`.`" . $tableName . "_id`" . ($property->description ? " " : "") . $property->description;
                        } else if (array_key_exists($type->type, $this->typeMap)) {
                            $sqlType = $this->typeMap[$type->type];
                        } else {
                            $sqlType = 'int';
                            $property->description = "references `" . $this->camelToSnake(substr($type->type, strlen($longestUniqueNamePrefix))) . "`.`id`" . ($property->description ? " " : "") . $property->description;
                        }

                        break;
                    }
                }

                if ($sqlType != null) {
                    $defaultValue = null;
                    $isNullable = false;

                    foreach ($property->types as $type) {
                        if ($type->type == null) {
                            $defaultValue = "NULL";
                            $isNullable = true;
                            break;
                        }
                    }

                    if ($property->defaultValue) {
                        if ($sqlType == 'varchar') {
                            $defaultValue = "\"" . $property->defaultValue . "\"";
                        } else {
                            $defaultValue = $property->defaultValue;
                        }
                    }

                    $column = "\t`" . $this->camelToSnake($property->variableName) . "` " . $sqlType . ($property->defaultValue ? " default " . $defaultValue : "") . ($isNullable ? "" : " NOT NULL") . ($property->description ? " COMMENT '" . addslashes($property->description) . "'" : "");

                    $columns[] = $column;
                }
            }

            $mySql[] = "CREATE TABLE `" . $tableName . "` (" . PHP_EOL . join("," . PHP_EOL, $columns) . PHP_EOL . ");";
        }

        return "# Generated with " . json_decode('"\u2764"') . " by GameMaker " . json_decode('"\u00B7"') . " https://github.com/iainconnor/game-maker." . PHP_EOL . PHP_EOL . join(PHP_EOL . PHP_EOL, $mySql);
    }

    /**
     * Returns objects that don't have any defined children.
     *
     * @param ObjectInformation[] $objects
     * @return ObjectInformation[]
     */
    protected function getTopLevelObjects(array $objects)
    {
        $topLevelObjects = [];

        foreach ($objects as $object) {
            foreach ($objects as $otherObject) {
                if ($object->class != $otherObject->class && get_parent_class($otherObject->class) !== false && get_parent_class($otherObject->class) == $object->class) {
                    continue(2);
                }
            }

            $topLevelObjects[] = $object;
        }

        return $topLevelObjects;
    }

    /**
     * @param string $string
     * @return string
     */
    protected function camelToSnake($string)
    {
        return parent::camelToSnake(str_replace('\\', '', $string));
    }

}