<?php

namespace LessQL;

/**
 * Represents a row of an SQL table (associative)
 */
class Row implements \ArrayAccess, \IteratorAggregate, \JsonSerializable
{

    /**
     * Constructor
     * Use $db->createRow() instead
     *
     * @param Database $db
     * @param string $name
     * @param array $properties
     * @param Result|null $result
     */
    public function __construct($db, $name, $properties = array(), $result = null)
    {
        $this->db_ = $db;
        $this->result_ = $result;
        $this->table_ = $this->db_->getAlias($name);

        $this->setData($properties);
    }

    /**
     * Get a property
     *
     * @param string $column
     * @return mixed
     */
    public function &__get($column)
    {
        if (!isset($this->properties_[$column])) {
            $null = null;
            return $null;
        }
        return $this->properties_[$column];
    }

    /**
     * Set a property
     *
     * @param string $column
     * @param mixed $value
     */
    public function __set($column, $value)
    {
        if (isset($this->properties_[$column]) && $this->properties_[$column] === $value) {
            return;
        }

        // convert arrays to Rows or list of Rows
        if (is_array($value)) {
            $name = preg_replace('/List$/', '', $column);
            $table = $this->getDatabase()->getAlias($name);

            if ($name === $column) { // row
                $value = $this->getDatabase()->createRow($table, $value);
            } else { // list
                foreach ($value as $i => $v) {
                    $value[$i] = $this->getDatabase()->createRow($table, $v);
                }
            }
        }
        $this->properties_[$column] = $value;
        $this->modified_[$column] = $value;
    }

    /**
     * Check if property is not null
     *
     * @param string $column
     * @return bool
     */
    public function __isset($column)
    {
        return isset($this->properties_[$column]);
    }

    /**
     * Remove a property from this row
     * Property will be ignored when saved, different to setting to null
     *
     * @param string $column
     * @return void
     */
    public function __unset($column)
    {
        unset($this->properties_[$column]);
        unset($this->modified_[$column]);
    }

    /**
     * Get referenced row(s) by name. Suffix "List" gets many rows using
     * a back reference.
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        array_unshift($args, $name);
        return call_user_func_array(array($this, 'referenced'), $args);
    }

    /**
     * Get referenced row(s) by name. Suffix "List" gets many rows using
     * a back reference.
     *
     * @param string $name
     * @param string|array|null $where
     * @param array $params
     * @return mixed
     */
    public function referenced($name, $where = null, $params = array())
    {
        $result = $this->getDatabase()->createResult($this, $name);
        if ($where !== null) {
            if (!is_array($params)) {
                $params = array_slice(func_get_args(), 2);
            }
            $result->where($where, $params);
        }
        return $result;
    }

    /**
     * Get the id
     *
     * @return string|array
     */
    public function getId()
    {
        $primary = $this->getDatabase()->getPrimary($this->getTable());
        if (is_array($primary)) {
            $id = array();
            foreach ($primary as $column) {
                if (!isset($this[$column])) {
                    return null;
                }
                $id[$column] = $this[$column];
            }
            return $id;
        }

        return $this[$primary];

    }

    /**
     * Get the row data
     *
     * @return array
     */
    public function getData()
    {
        $data = array();
        foreach ($this->properties_ as $column => $value) {
            if ($value instanceof Row || is_array($value)) {
                continue;
            }
            $data[$column] = $value;
        }
        return $data;
    }

    /**
     * Set row data (extends the row)
     *
     * @param array $data
     * @return $this
     */
    public function setData($data)
    {
        foreach ($data as $column => $value) {
            $this->__set($column, $value);
        }
        return $this;
    }

    /**
     * Get the original id
     *
     * @return string|array
     */
    public function getOriginalId()
    {
        return $this->originalId_;
    }

    /**
     * Get modified data
     *
     * @return array
     */
    public function getModified()
    {
        $modified = array();
        foreach ($this->modified_ as $column => $value) {
            if ($value instanceof Row || is_array($value)) {
                continue;
            }
            $modified[$column] = $value;
        }
        return $modified;
    }

    /**
     * Save this row
     * Also saves nested rows if $recursive is true (default)
     *
     * @param bool $recursive
     * @return $this
     * @throws \LogicException
     */
    public function save($recursive = true)
    {
        $db = $this->getDatabase();
        $table = $this->getTable();
        if (!$recursive) { // just save the row
            $this->updateReferences();
            if (!$this->isClean()) {
                $primary = $db->getPrimary($table);
                if ($this->exists()) {
                    $idCondition = $this->getOriginalId();
                    if (!is_array($idCondition)) {
                        $idCondition = array($primary => $idCondition);
                    }
                    $db
                        ->table($table)
                        ->where($idCondition)
                        ->update($this->getModified());

                    $this->setClean();
                } else {
                    $db
                        ->table($table)
                        ->insert($this->getData());

                    if (!is_array($primary) && !isset($this[$primary])) {
                        $id = $db->lastInsertId($db->getSequence($table));
                        if (isset($id)) {
                            $this[$primary] = $id;
                        }
                    }
                    $this->setClean();
                }
            }
            return $this;
        }

        // make list of all rows in this tree

        $list = array();
        $this->listRows($list);
        $count = count($list);

        // keep iterating and saving until all references are known

        while (true) {
            $solvable = false;
            $clean = 0;
            /** @var Row $row */
            foreach ($list as $row) {
                $row->updateReferences();
                $missing = $row->getMissing();

                if (empty($missing)) {
                    $row->save(false);
                    $row->updateBackReferences();
                    $solvable = true;
                }
                if ($row->isClean()) {
                    ++$clean;
                }
            }

            if (!$solvable) {
                throw new \LogicException(
                    "Cannot recursively save structure (" . $table . ") - add required values or allow NULL"
                );
            }

            if ($clean === $count) {
                break;
            }
        }
        return $this;
    }

    /**
     * @param array $list
     */
    protected function listRows(&$list)
    {
        $list[] = $this;

        foreach ($this->properties_ as $column => $value) {
            if ($value instanceof Row) {
                $value->listRows($list);
            } elseif (is_array($value)) {
                /** @var Row $row */
                foreach ($value as $row) {
                    $row->listRows($list);
                }
            }
        }
    }

    /**
     * Check references and set respective keys
     * Returns list of keys to unknown references
     *
     * @return array
     */
    public function updateReferences()
    {
        $unknown = array();
        foreach ($this->properties_ as $column => $value) {
            if ($value instanceof Row) {
                $key = $this->getDatabase()->getReference($this->getTable(), $column);
                $this[$key] = $value->getId();
            }
        }
        return $unknown;

    }

    /**
     * Check back references and set respective keys
     *
     * @return $this|null
     */
    public function updateBackReferences()
    {
        $id = $this->getId();
        if (is_array($id)) {
            return null;
        }

        foreach ($this->properties_ as $column => $value) {
            if (is_array($value)) {
                $key = $this->getDatabase()->getBackReference($this->getTable(), $column);
                foreach ($value as $row) {
                    $row->{$key} = $id;
                }
            }
        }

        return $this;
    }

    /**
     * Get missing columns, i.e. any that is null but required by the
     * schema
     *
     * @return array
     */
    public function getMissing()
    {
        $missing = array();
        $required = $this->getDatabase()->getRequired($this->getTable());
        foreach ($required as $column => $true) {
            if (!isset($this[$column])) {
                $missing[] = $column;
            }
        }
        return $missing;
    }

    /**
     * Update this row directly
     *
     * @param $data
     * @param bool $recursive
     * @return $this
     */
    public function update($data, $recursive = true)
    {
        return $this->setData($data)->save($recursive);
    }

    /**
     * Delete this row
     *
     * @return $this|Row
     */
    public function delete()
    {
        $result = $this->getDatabase()->table($this->getTable());
        $idCondition = $this->getOriginalId();

        if ($idCondition === null) {
            return $this;
        }

        if (!is_array($idCondition)) {
            $primary = $this->getDatabase()->getPrimary($this->getTable());
            $idCondition = array($primary => $idCondition);
        }

        $result->where($idCondition)->delete();
        $this->originalId_ = null;
        return $this->setDirty();
    }

    /**
     * Does this row exist?
     *
     * @return bool
     */
    public function exists()
    {
        return $this->originalId_ !== null;
    }

    /**
     * Is this row clean, i.e. in sync with the database?
     *
     * @return bool
     */
    public function isClean()
    {
        return empty($this->modified_);
    }

    /**
     * Set this row to "clean" state, i.e. in sync with database
     *
     * @return $this
     */
    public function setClean()
    {
        $id = $this->getId();
        if ($id === null) {
            throw new \LogicException('Cannot set Row "clean" without id');
        }
        $this->originalId_ = $id;
        $this->modified_ = array();

        return $this;
    }

    /**
     * Set this row to "dirty" state, i.e. out of sync with database
     *
     * @return $this
     */
    public function setDirty()
    {
        $this->modified_ = $this->properties_; // copy...
        return $this;
    }

    /**
     * Get root result
     *
     * @return $this|Result
     */
    public function getRoot()
    {
        $result = $this->getResult();
        if ($result) {
            return $result->getRoot();
        }
        return $this;
    }

    /**
     * Get value from cache
     *
     * @param $key
     * @return mixed
     */
    public function getCache($key)
    {
        return isset($this->cache_[$key]) ? $this->cache_[$key] : null;
    }

    /**
     * Set cache value
     *
     * @param string $key
     * @param mixed $value
     */
    public function setCache($key, $value)
    {
        $this->cache_[$key] = $value;
    }

    /**
     * Get column, used by result if row is parent
     *
     * @param string $key
     * @return array
     */
    public function getLocalKeys($key)
    {
        if (isset($this[$key])) {
            return array($this[$key]);
        }
        return array();
    }

    /**
     * Get global keys of parent result, or column if row is root
     *
     * @param string $key
     * @return array
     */
    public function getGlobalKeys($key)
    {
        $result = $this->getResult();
        if ($result) {
            return $result->getGlobalKeys($key);
        }
        return $this->getLocalKeys($key);
    }

    /**
     * Get the database
     *
     * @return Database
     */
    public function getDatabase()
    {
        return $this->db_;
    }

    /**
     * Get the bound result, if any
     *
     * @return Result|null
     */
    public function getResult()
    {
        return $this->result_;
    }

    /**
     * Get the table
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table_;
    }

    // ArrayAccess

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function &offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->__set($offset, $value);
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    // IteratorAggregate

    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->properties_);
    }

    // JsonSerializable

    /**
     * @return array
     */
    public function jsonSerialize()
    {

        $array = array();

        foreach ($this->properties_ as $key => $value) {
            if ($value instanceof \JsonSerializable) {
                $array[$key] = $value->jsonSerialize();
            } elseif ($value instanceof \DateTime) {
                $array[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value)) { // list of Rows
                /**
                 * @var int $i
                 * @var Row $row
                 */
                foreach ($value as $i => $row) {
                    $value[$i] = $row->jsonSerialize();
                }
                $array[$key] = $value;
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    //

    /** @var Database */
    protected $db_;

    /** @var string */
    protected $table_;

    /** @var Result|null */
    protected $result_;

    /** @var array */
    protected $properties_;

    /** @var array */
    protected $modified_;

    /** @var string|array  */
    protected $originalId_;

    //

    /** @var array */
    protected $cache_ = array();
}
