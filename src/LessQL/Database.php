<?php

namespace LessQL;

/**
 * Base object wrapping a PDO instance
 */
class Database
{

    /**
     * Constructor. Sets PDO to exception.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        // required for safety
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo = $pdo;
    }

    /**
     * Returns a result for table $name.
     * If $id is given, return the row with that id.
     *
     * Examples:
     * $db->user()->where( ... )
     * $db->user( 1 )
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        array_unshift($args, $name);
        return call_user_func_array(array($this, 'table'), $args);
    }

    /**
     * Returns a result for table $name.
     * If $id is given, return the row with that id.
     *
     * @param $name
     * @param int|null $id
     * @return Result
     */
    public function table($name, $id = null)
    {
        // ignore List suffix
        $name = preg_replace('/List$/', '', $name);

        if ($id !== null) {
            $result = $this->createResult($this, $name);

            if (!is_array($id)) {
                $table = $this->getAlias($name);
                $primary = $this->getPrimary($table);
                $id = array($primary => $id);
            }

            return $result->where($id)->fetch();
        }

        return $this->createResult($this, $name);
    }

    // Factories

    /**
     * Create a row from given properties.
     * Optionally bind it to the given result.
     *
     * @param string $name
     * @param array $properties
     * @param Result|null $result
     * @return Row
     */
    public function createRow($name, $properties = array(), $result = null)
    {
        return new Row($this, $name, $properties, $result);
    }

    /**
     * Create a result bound to $parent using table or association $name.
     * $parent may be the database, a result, or a row
     *
     * @param Database|Result|Row $parent
     * @param string $name
     * @return Result
     */
    public function createResult($parent, $name)
    {
        return new Result($parent, $name);
    }

    // PDO interface

    /**
     * Prepare an SQL statement
     *
     * @param string $query
     * @return \PDOStatement
     */
    public function prepare($query)
    {
        return $this->pdo->prepare($query);
    }

    /**
     * Return last inserted id
     *
     * @param string|null $sequence
     * @return string
     */
    public function lastInsertId($sequence = null)
    {
        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function begin()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit changes of transaction
     *
     * @return bool
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback any changes during transaction
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }

    // Schema hints

    /**
     * Get primary key of a table, may be array for compound keys
     *
     * Convention is "id"
     *
     * @param $table
     * @return string|array
     */
    public function getPrimary($table)
    {
        if (isset($this->primary[$table])) {
            return $this->primary[$table];
        }
        return 'id';
    }

    /**
     * Set primary key of a table, may be array for compound keys.
     * Always set it for tables with compound primary keys.
     *
     * @param string $table
     * @param string|array $key
     * @return $this
     */
    public function setPrimary($table, $key)
    {
        $this->primary[$table] = $key;
        // compound keys are never auto-generated,
        // so we can assume they are required
        if (is_array($key)) {
            foreach ($key as $k) {
                $this->setRequired($table, $k);
            }
        }
        return $this;
    }

    /**
     * Get a reference key for a association on a table
     *
     * "How would $table reference another table under $name?"
     *
     * Convention is "$name_id"
     *
     * @param string $table
     * @param string $name
     * @return string
     */
    public function getReference($table, $name)
    {
        if (isset($this->references[$table][$name])) {
            return $this->references[$table][$name];
        }
        return $name . '_id';
    }

    /**
     * Set a reference key for a association on a table
     *
     * @param string $table
     * @param string $name
     * @param string $key
     * @return $this
     */
    public function setReference($table, $name, $key)
    {
        $this->references[$table][$name] = $key;
        return $this;
    }

    /**
     * Get a back reference key for a association on a table
     *
     * "How would $table be referenced by another table under $name?"
     *
     * Convention is "$table_id"
     *
     * @param string $table
     * @param string $name
     * @return string
     */
    public function getBackReference($table, $name)
    {
        if (isset($this->backReferences[$table][$name])) {
            return $this->backReferences[$table][$name];
        }
        return $table . '_id';
    }

    /**
     * Set a back reference key for a association on a table
     *
     * @param string $table
     * @param string $name
     * @param string $key
     * @return $this
     */
    public function setBackReference($table, $name, $key)
    {
        $this->backReferences[$table][$name] = $key;
        return $this;
    }

    /**
     * Get alias of a table
     *
     * @param string $alias
     * @return string
     */
    public function getAlias($alias)
    {
        return isset($this->aliases[$alias]) ? $this->aliases[$alias] : $alias;
    }

    /**
     * Set alias of a table
     *
     * @param string $alias
     * @param string $table
     * @return $this
     */
    public function setAlias($alias, $table)
    {
        $this->aliases[$alias] = $table;
        return $this;
    }

    /**
     * Is a column of a table required for saving? Default is no
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function isRequired($table, $column)
    {
        return isset($this->required[$table][$column]);
    }

    /**
     * Get a map of required columns of a table
     *
     * @param string $table
     * @return array
     */
    public function getRequired($table)
    {
        return isset($this->required[$table]) ? $this->required[$table] : array();
    }

    /**
     * Set a column to be required for saving
     * Any primary key that is not auto-generated should be required
     * Compound primary keys are required by default
     *
     * @param string $table
     * @param string $column
     * @return $this
     */
    public function setRequired($table, $column)
    {
        $this->required[$table][$column] = true;
        return $this;
    }

    /**
     * Get primary sequence name of table (used in INSERT by Postgres)
     *
     * Conventions is "$tableRewritten_$primary_seq"
     *
     * @param string $table
     * @return null|string
     */
    public function getSequence($table)
    {
        if (isset($this->sequences[$table])) {
            return $this->sequences[$table];
        }
        $primary = $this->getPrimary($table);
        $table = $this->rewriteTable($table);

        if (is_array($primary)) {
            return null;
        }

        return $table . '_' . $primary . '_seq';
    }

    /**
     * Set primary sequence name of table
     *
     * @param string $table
     * @param string $sequence
     */
    public function setSequence($table, $sequence)
    {
        $this->sequences[$table] = $sequence;
    }

    /**
     * Get rewritten table name
     *
     * @param string $table
     * @return string
     */
    public function rewriteTable($table)
    {
        if (is_callable($this->rewrite)) {
            return call_user_func($this->rewrite, $table);
        }
        return $table;
    }

    /**
     * Set table rewrite function
     * For example, it could add a prefix
     *
     * @param callable $rewrite
     */
    public function setRewrite($rewrite)
    {
        $this->rewrite = $rewrite;
    }

    // SQL style

    /**
     * Get identifier delimiter
     *
     * @return string
     */
    public function getIdentifierDelimiter()
    {
        return $this->identifierDelimiter;
    }

    /**
     * Sets delimiter used when quoting identifiers. Should be backtick
     * or double quote. Set to null to disable quoting.
     *
     * @param string|null $d
     * @return void
     */
    public function setIdentifierDelimiter($d)
    {
        $this->identifierDelimiter = $d;
    }

    // Queries

    /**
     * Select rows from a table
     *
     * @param string $table
     * @param mixed $exprs
     * @param array $where
     * @param array $orderBy
     * @param int|null $limitCount
     * @param int|null $limitOffset
     * @param array $params
     * @return \PDOStatement
     */
    public function select(
        $table,
        $exprs = null,
        $where = array(),
        $orderBy = array(),
        $limitCount = null,
        $limitOffset = null,
        $params = array()
    ) {

        $query = "SELECT ";

        if (empty($exprs)) {
            $query .= "*";
        } elseif (is_array($exprs)) {
            $query .= implode(", ", $exprs);
        } else {
            $query .= $exprs;
        }

        $table = $this->rewriteTable($table);
        $query .= " FROM " . $this->quoteIdentifier($table);

        $query .= $this->getSuffix($where, $orderBy, $limitCount, $limitOffset);

        $this->onQuery($query, $params);

        $statement = $this->prepare($query);
        $statement->setFetchMode(\PDO::FETCH_ASSOC);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Insert one ore more rows into a table
     *
     * The $method parameter selects one of the following insert methods:
     *
     * "prepared": Prepare a query and execute it once per row using bound params
     *             Does not support Literals in row data (PDO limitation)
     *
     * "batch":    Create a single query mit multiple value lists
     *             Supports Literals, but not supported everywhere
     *
     * default:    Execute one INSERT per row
     *             Supports Literals, supported everywhere, slow for many rows
     *
     * @param string $table
     * @param array $rows
     * @param string|null $method
     * @return \PDOStatement|null
     */
    public function insert($table, $rows, $method = null)
    {

        $statement = null;

        if (empty($rows)) {
            return null;
        }

        if (!isset($rows[0])) {
            $rows = array($rows);
        }

        // get ALL columns

        $columns = array();

        foreach ($rows as $row) {
            foreach ($row as $column => $value) {
                $columns[$column] = true;
            }
        }

        $columns = array_keys($columns);

        if (empty($columns)) {
            return null;
        }

        // query head

        $quotedColumns = array_map(array($this, 'quoteIdentifier'), $columns);
        $table = $this->rewriteTable($table);
        $query = "INSERT INTO " . $this->quoteIdentifier($table);
        $query .= " ( " . implode(", ", $quotedColumns) . " ) VALUES ";

        if ($method === 'prepared') {
            // prepare query and execute once per row
            $query .= "( ?" . str_repeat(", ?", count($columns) - 1) . " )";

            $statement = $this->prepare($query);

            foreach ($rows as $row) {
                $values = array();

                foreach ($columns as $column) {
                    $value = (string)$this->format(@$row[$column]);
                    $values[] = $value;
                }

                $this->onQuery($query, $values);

                $statement->execute($values);
            }

        } else {
            // build value lists without params
            $lists = array();

            foreach ($rows as $row) {
                $values = array();
                foreach ($columns as $column) {
                    $values[] = $this->quote(@$row[$column]);
                }
                $lists[] = "( " . implode(", ", $values) . " )";
            }

            if ($method === 'batch') {
                // batch all rows into one query
                $query .= implode(", ", $lists);

                $this->onQuery($query);

                $statement = $this->prepare($query);
                $statement->execute();
            } else {
                // execute one insert per row
                foreach ($lists as $list) {
                    $q = $query . $list;

                    $this->onQuery($q);

                    $statement = $this->prepare($q);
                    $statement->execute();
                }
            }
        }

        return $statement;
    }

    /**
     * Execute update query and return statement
     *
     * UPDATE $table SET $data [WHERE $where]
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @param array $params
     * @return null|\PDOStatement
     */
    public function update($table, $data, $where = array(), $params = array())
    {

        if (empty($data)) {
            return null;
        }

        $set = array();

        foreach ($data as $column => $value) {
            $set[] = $this->quoteIdentifier($column) . " = " . $this->quote($value);
        }

        if (!is_array($where)) {
            $where = array($where);
        }
        if (!is_array($params)) {
            $params = array_slice(func_get_args(), 3);
        }

        $table = $this->rewriteTable($table);
        $query = "UPDATE " . $this->quoteIdentifier($table);
        $query .= " SET " . implode(", ", $set);
        $query .= $this->getSuffix($where);

        $this->onQuery($query, $params);

        $statement = $this->prepare($query);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Execute delete query and return statement
     *
     * DELETE FROM $table [WHERE $where]
     *
     * @param string $table
     * @param array $where
     * @param array $params
     * @return \PDOStatement
     */
    public function delete($table, $where = array(), $params = array())
    {

        if (!is_array($where)) {
            $where = array($where);
        }
        if (!is_array($params)) {
            $params = array_slice(func_get_args(), 2);
        }

        $table = $this->rewriteTable($table);
        $query = "DELETE FROM " . $this->quoteIdentifier($table);
        $query .= $this->getSuffix($where);

        $this->onQuery($query, $params);

        $statement = $this->prepare($query);
        $statement->execute($params);

        return $statement;
    }

    // SQL utility

    /**
     * Return WHERE/LIMIT/ORDER suffix for queries
     *
     * @param array $where
     * @param array $orderBy
     * @param int|null $limitCount
     * @param int|null $limitOffset
     * @return string
     */
    protected function getSuffix($where, $orderBy = array(), $limitCount = null, $limitOffset = null)
    {

        $suffix = "";

        if (!empty($where)) {
            $suffix .= " WHERE " . implode(" AND ", $where);
        }

        if (!empty($orderBy)) {
            $suffix .= " ORDER BY " . implode(", ", $orderBy);
        }

        if (isset($limitCount)) {
            $suffix .= " LIMIT " . intval($limitCount);

            if (isset($limitOffset)) {
                $suffix .= " OFFSET " . intval($limitOffset);
            }
        }

        return $suffix;
    }

    /**
     * Build an SQL condition expressing that "$column is $value",
     * or "$column is in $value" if $value is an array. Handles null
     * and literals like new Literal( "NOW()" ) correctly.
     *
     * @param string $column
     * @param string|array $value
     * @param bool $not
     * @return string
     */
    public function is($column, $value, $not = false)
    {

        $bang = $not ? "!" : "";
        $or = $not ? " AND " : " OR ";
        $novalue = $not ? "1=1" : "0=1";
        $not = $not ? " NOT" : "";

        // always treat value as array
        if (!is_array($value)) {
            $value = array($value);
        }

        // always quote column identifier
        $column = $this->quoteIdentifier($column);

        if (count($value) === 1) {
            // use single column comparison if count is 1
            $value = $value[0];

            if ($value === null) {
                return $column . " IS" . $not . " NULL";
            } else {
                return $column . " " . $bang . "= " . $this->quote($value);
            }

        } elseif (count($value) > 1) {
            // if we have multiple values, use IN clause

            $values = array();
            $null = false;

            foreach ($value as $v) {
                if ($v === null) {
                    $null = true;
                } else {
                    $values[] = $this->quote($v);
                }
            }

            $clauses = array();

            if (!empty($values)) {
                $clauses[] = $column . $not . " IN ( " . implode(", ", $values) . " )";
            }

            if ($null) {
                $clauses[] = $column . " IS" . $not . " NULL";
            }

            return implode($or, $clauses);

        }

        return $novalue;

    }

    /**
     * Build an SQL condition expressing that "$column is not $value"
     * or "$column is not in $value" if $value is an array. Handles null
     * and literals like new Literal( "NOW()" ) correctly.
     *
     * @param string $column
     * @param string|array $value
     * @return string
     */
    public function isNot($column, $value)
    {
        return $this->is($column, $value, true);
    }

    /**
     * Quote a value for SQL
     *
     * @param mixed $value
     * @return string
     */
    public function quote($value)
    {
        $value = $this->format($value);

        if ($value === null) {
            return "NULL";
        }

        if ($value === false) {
            return "'0'";
        }

        if ($value === true) {
            return "'1'";
        }

        if (is_int($value)) {
            return "'" . ((string)$value) . "'";
        }

        if (is_float($value)) {
            return "'" . sprintf("%F", $value) . "'";
        }

        if ($value instanceof Literal) {
            return $value->value;
        }

        return $this->pdo->quote($value);
    }

    /**
     * Format a value for SQL, e.g. DateTime objects
     *
     * @param mixed $value
     * @return string
     */
    public function format($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format("Y-m-d H:i:s");
        }

        return $value;
    }

    /**
     * Quote identifier
     *
     * @param string $identifier
     * @return string
     */
    public function quoteIdentifier($identifier)
    {

        $d = $this->identifierDelimiter;

        if (empty($d)) {
            return $identifier;
        }

        $identifier = explode(".", $identifier);

        $identifier = array_map(
            function ($part) use ($d) {
                return $d . str_replace($d, $d . $d, $part) . $d;
            },
            $identifier
        );

        return implode(".", $identifier);

    }

    /**
     * Create a SQL Literal
     *
     * @param string $value
     * @return Literal
     */
    public function literal($value)
    {
        return new Literal($value);
    }

    //

    /**
     * Calls the query callback, if any
     *
     * @param string $query
     * @param array $params
     */
    protected function onQuery($query, $params = array())
    {
        if (is_callable($this->queryCallback)) {
            call_user_func($this->queryCallback, $query, $params);
        }
    }

    /**
     * Set the query callback
     *
     * @param callable $callback
     */
    public function setQueryCallback($callback)
    {
        $this->queryCallback = $callback;
    }

    //

    /** @var string */
    protected $identifierDelimiter = "`";

    //

    /** @var array */
    protected $primary = array();

    /** @var array */
    protected $references = array();

    /** @var array */
    protected $backReferences = array();

    /** @var array */
    protected $aliases = array();

    /** @var array */
    protected $required = array();

    /** @var array */
    protected $sequences = array();

    /** @var callable */
    protected $rewrite;

    //

    /** @var callable */
    protected $queryCallback;
}
