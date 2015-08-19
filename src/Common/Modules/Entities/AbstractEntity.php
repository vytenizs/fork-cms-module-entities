<?php

namespace Common\Modules\Entities;

use Common\Core\Model as CommonModel;

/**
 * Class AbstractEntity
 * @package Common\Modules\Entities
 */
abstract class AbstractEntity
{
    /**
     * @var \SpoonDatabase
     */
    protected $_db;

    /**
     * @var string
     */
    protected $_table;

    /**
     * @var string
     */
    protected $_query;

    /**
     * @var array
     */
    protected $_primary = array('id');

    /**
     * @var array
     */
    protected $_columns = array();

    /**
     * @var array
     */
    protected $_relations = array();

    /**
     * @var bool
     */
    protected $_loaded = false;

    /**
     * @var int
     */
    protected $id;

    /**
     *
     */
    function __construct()
    {
        $this->_db = CommonModel::getContainer()->get('database');
        $this->_columns = array_unique(array_merge($this->_primary, $this->_columns));
    }

    /**
     * @param array $parameters
     * @return $this
     * @throws \SpoonDatabaseException
     */
    public function load($parameters = array())
    {
        if (!empty($this->_query)) {
            $record = (array)$this->_db->getRecord(
                $this->_query,
                $parameters
            );

            $this->assemble($record);
        }

        return $this;
    }

    /**
     * @param $record
     * @return $this
     */
    public function assemble($record)
    {
        $this->_loaded = true;

        if (empty($this->_columns)) {
            $this->_columns = array_keys($record);
        }

        foreach ($record as $recordKey => $recordValue) {
            $setMethod = 'set' . \SpoonFilter::toCamelCase($recordKey);
            if (method_exists($this, $setMethod) && $recordValue !== null) {
                $this->$setMethod($recordValue);
            }
        }

        return $this;
    }

    /**
     * @param bool $includeRelations
     * @return array
     */
    protected function getVariables($includeRelations = true)
    {
        $result = array();
        $objectVariables = get_object_vars($this);

        $entityVariables = array_merge($this->_primary, $this->_columns);

        if ($includeRelations) {
            $entityVariables = array_merge($entityVariables, $this->_relations);
        }

        $entityVariables = array_unique($entityVariables);

        foreach ($objectVariables as $objectVariablesKey => $objectVariablesValue) {
            if (in_array(Helper::toSnakeCase($objectVariablesKey), $entityVariables)) {
                $result[$objectVariablesKey] = $objectVariablesValue;
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function isAffected()
    {
        $variables = array_filter(
            $this->getVariables(),
            '\\Common\\Modules\\Entities\\Helper::filterNotNull'
        );

        return !empty($variables);
    }

    /**
     * @return bool
     */
    public function isLoaded()
    {
        return is_numeric($this->id);
    }

    /**
     * @return string
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * @param $table
     * @return $this
     */
    public function setTable($table)
    {
        $this->_table = $table;

        return $this;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->_query;
    }

    /**
     * @param $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->_query = $query;

        return $this;
    }

    /**
     * @param $column
     * @return bool
     */
    public function hasColumn($column)
    {
        return in_array($column, $this->_columns);
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        return $this->_columns;
    }

    /**
     * @param $relation
     * @return bool
     */
    public function hasRelation($relation)
    {
        return in_array($relation, $this->_relations);
    }

    /**
     * @return array
     */
    public function getRelations()
    {
        return $this->_relations;
    }

    /**
     * @param $relation
     * @return $this
     */
    public function addRelation($relation)
    {
        if (!in_array($relation, $this->_relations)) {
            $this->_relations[] = $relation;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = (int)$id;

        return $this;
    }

    /**
     * @param bool $onlyColumns
     * @return array
     */
    public function toArray($onlyColumns = false)
    {
        $result = array();

        foreach ($this->getVariables(!$onlyColumns) as $variablesKey => $variablesValue) {
            $variablesKey = Helper::toSnakeCase($variablesKey);
            if (is_array($variablesValue)) {
                foreach ($variablesValue as $variablesValueKey => &$variablesValueValue) {
                    $variablesValueKey = Helper::toSnakeCase($variablesValueKey);
                    if ($variablesValueValue instanceof AbstractEntity) {
                        $result[$variablesKey][$variablesValueKey] = $variablesValueValue->toArray();
                    }
                }
                continue;
            }
            $result[$variablesKey] = $variablesValue;
        }

        return $result;
    }

    /**
     * @return int
     */
    public function save()
    {
        if (empty($this->id) || !$this->_loaded) {
            $id = $this->insert();

            if ($this->id === null) {
                $this->setId($id);
            }

            return $this;
        }

        $this->update();

        return $this;
    }

    /**
     * @return int
     * @throws \SpoonDatabaseException
     */
    public function insert()
    {
        $arrayToSave = array_filter($this->toArray(true), '\\Common\\Modules\\Entities\\Helper::filterValuable');

        return (int)$this->_db->insert($this->_table, $arrayToSave);
    }

    /**
     * @return int
     * @throws \Exception
     * @throws \SpoonDatabaseException
     */
    public function update()
    {
        $arrayToSave = array_filter($this->toArray(true), '\\Common\\Modules\\Entities\\Helper::filterValuable');

        $where = array();
        $whereValues = array();

        Helper::generateWhereClauseVariables($this->_primary, $this->_table, $arrayToSave, $where, $whereValues);

        return (int)$this->_db->update($this->_table, $arrayToSave, implode(' AND ', $where), $whereValues);
    }

    /**
     * @throws \Exception
     * @throws \SpoonDatabaseException
     */
    public function delete()
    {
        $arrayToSave = array_filter($this->toArray(true), '\\Common\\Modules\\Entities\\Helper::filterValuable');

        $where = array();
        $whereValues = array();

        Helper::generateWhereClauseVariables($this->_primary, $this->_table, $arrayToSave, $where, $whereValues);

        $this->_db->delete($this->_table, implode(' AND ', $where), $whereValues);

        $this->_loaded = false;
    }
}
