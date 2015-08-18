<?php

namespace LessQL;

/**
 * Represents a filtered table result.
 *
 *  SELECT
 * 		{* | select_expr, ...}
 * 		FROM table
 * 			[WHERE condition [AND condition [...]]]
 * 			[ORDER BY {col_name | expr | position} [ASC | DESC], ...]
 * 			[LIMIT count [OFFSET offset]]
 *
 * TODO Add more SQL dialect specifics like FETCH FIRST, TOP etc.
 */
class Result implements \IteratorAggregate, \JsonSerializable {

	/**
	 * Constructor
	 * Use $db->createResult( $parent, $name ) instead
	 *
	 * @param Database|Result|Row $parent
	 * @param string $name
	 */
	function __construct( $parent, $name ) {

		if ( $parent instanceof Database ) {

			// basic result

			$this->db = $parent;
			$this->table = $this->db->getAlias( $name );

		} else { // Row or Result

			// result referenced to parent

			$this->parent_ = $parent;
			$this->db = $parent->getDatabase();

			// determine type of reference based on conventions and user hints

			$fullName = $name;
			$name = preg_replace( '/List$/', '', $fullName );

			$this->table = $this->db->getAlias( $name );

			$this->single = $name === $fullName;

			if ( $this->single ) {

				$this->key = $this->db->getPrimary( $this->getTable() );
				$this->parentKey = $this->db->getReference( $parent->getTable(), $name );

			} else {

				$this->key = $this->db->getBackReference( $parent->getTable(), $name );
				$this->parentKey = $this->db->getPrimary( $parent->getTable() );

			}

		}

	}

	/**
	 * Get referenced row(s) by name. Suffix "List" gets many rows
	 * Arguments are passed to where( $where, $params )
	 *
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	function __call( $name, $args ) {

		array_unshift( $args, $name );

		return call_user_func_array( array( $this, 'referenced' ), $args );

	}

	/**
	 * Get referenced row(s) by name. Suffix "List" gets many rows
	 *
	 * @param string $name
	 * @param string|array|null $where
	 * @param array $params
	 * @return Result
	 */
	function referenced( $name, $where = null, $params = array() ) {

		$result = $this->db->createResult( $this, $name );

		if ( $where !== null ) {

			if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 2 );
			$result = $result->where( $where, $params );

		}

		return $result;

	}

	/**
	 * Create result with new reference key
	 *
	 * @param string $key
	 * @return Result
	 */
	function via( $key ) {

		if ( !$this->parent_ ) throw new \LogicException( 'Cannot set reference key on basic Result' );

		$clone = clone $this;

		if ( $clone->single ) {

			$clone->parentKey = $key;

		} else {

			$clone->key = $key;

		}

		return $clone;

	}

	/**
	 * Execute the select query defined by this result.
	 *
	 * @return $this
	 */
	function execute() {

		if ( isset( $this->rows ) ) return $this;

		if ( $this->parent_ ) {

			// restrict to parent
			$this->where[] = $this->db->is( $this->key, $this->parent_->getGlobalKeys( $this->parentKey ) );

		}

		$root = $this->getRoot();
		$definition = $this->getDefinition();

		$cached = $root->getCache( $definition );

		if ( !$cached ) {

			// fetch all rows
			$statement = $this->db->select( $this->table, array(
				'expr' => $this->select,
				'where' => $this->where,
				'orderBy' => $this->orderBy,
				'limitCount' => $this->limitCount,
				'limitOffset' => $this->limitOffset,
				'params' => $this->whereParams
			) );

			$rows = $statement->fetchAll();
			$cached = array();

			// build row objects
			foreach ( $rows as $row ) {

				$row = $this->createRow( $row );
				$row->setClean();

				$cached[] = $row;

			}

			$root->setCache( $definition, $cached );

		}

		$this->globalRows = $cached;

		if ( !$this->parent_ ) {

			$this->rows = $cached;

		} else {

			$this->rows = array();
			$keys = $this->parent_->getLocalKeys( $this->parentKey );

			foreach ( $cached as $row ) {

				if ( in_array( $row[ $this->key ], $keys ) ) {

					$this->rows[] = $row;

				}

			}

		}

		return $this;

	}

	/**
	 * Create a Row for this result's table
	 * The row is bound to this result
	 *
	 * @param array $data Row data
	 * @return Row
	 */
	function createRow( $data ) {

		return $this->db->createRow( $this->table, $data, $this );

	}

	/**
	 * Get the database
	 *
	 * @return Database
	 */
	function getDatabase() {

		return $this->db;

	}

	/**
	 * Get the root result
	 *
	 * @return Result
	 */
	function getRoot() {

		if ( !$this->parent_ ) {

			return $this;

		}

		return $this->parent_->getRoot();

	}

	/**
	 * Get the table of this result
	 *
	 * @return string
	 */
	function getTable() {

		return $this->table;

	}

	/**
	 * Get $key values of this result
	 *
	 * @param string $key
	 * @return array
	 */
	function getLocalKeys( $key ) {

		$this->execute();

		return $this->getKeys( $this->rows, $key );

	}

	/**
	 * Get global $key values of the result, i.e., disregarding its parent
	 *
	 * @param string $key
	 * @return array
	 */
	function getGlobalKeys( $key ) {

		$this->execute();

		return $this->getKeys( $this->globalRows, $key );

	}

	/**
	 * Get $key values of given rows
	 *
	 * @param Row[] $rows
	 * @param string $key
	 * @return array
	 */
	protected function getKeys( $rows, $key ) {

		if ( count( $rows ) > 0 && !$rows[ 0 ]->hasProperty( $key ) ) {

			throw new \LogicException( '"' . $key . '" does not exist in "' . $this->table . '" result' );

		}

		$keys = array();

		foreach ( $rows as $row ) {

			if ( isset( $row->{ $key } ) ) {

				$keys[] = $row->{ $key };

			}

		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Get value from cache
	 *
	 * @param string $key
	 * @return null|mixed
	 */
	function getCache( $key ) {

		return isset( $this->_cache[ $key ] ) ? $this->_cache[ $key ] : null;

	}

	/**
	 * Set cache value
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return $this;
	 */
	function setCache( $key, $value ) {

		$this->_cache[ $key ] = $value;

		return $this;

	}

	/**
	 * Is this result a single association, i.e. not a list of rows?
	 *
	 * @return bool
	 */
	function isSingle() {

		return $this->single;

	}

	/**
	 * Fetch the next row in this result
	 *
	 * @return Row
	 */
	function fetch() {

		$this->execute();

		list( $index, $row ) = each( $this->rows );

		return $row;

	}

	/**
	 * Fetch all rows in this result
	 *
	 * @return Row[]
	 */
	function fetchAll() {

		$this->execute();

		return $this->rows;

	}

	/**
	 * Return number of rows in this result
	 *
	 * @return int
	 */
	function rowCount() {

		$this->execute();

		return count( $this->rows );

	}

	// Manipulation

	/**
	 * Insert one ore more rows into the table of this result
	 * See Database::insert for information on $method
	 *
	 * @param array $rows
	 * @param string|null $method
	 * @return null|\PDOStatement
	 */
	function insert( $rows, $method = null ) {

		return $this->db->insert( $this->table, $rows, $method );

	}

	/**
	 * Update the rows matched by this result, setting $data
	 *
	 * @param array $data
	 * @return null|\PDOStatement
	 */
	function update( $data ) {

		// if this is an association result or it is limited,
		// create specific result for local rows and execute

		if ( $this->parent_ || isset( $this->limitCount ) ) {

			return $this->primaryResult()->update( $data );

		}

		return $this->db->update( $this->table, $data, $this->where, $this->whereParams );

	}

	/**
	 * Delete all rows matched by this result
	 *
	 * @return \PDOStatement
	 */
	function delete() {

		// if this is an association result or it is limited,
		// create specific result for local rows and execute

		if ( $this->parent_ || isset( $this->limitCount ) ) {

			return $this->primaryResult()->delete();

		}

		return $this->db->delete( $this->table, $this->where, $this->whereParams );

	}

	/**
	 * Return a new basic result which selects all rows in this result by primary key
	 *
	 * @return Result
	 */
	function primaryResult() {

		$result = $this->db->table( $this->table );
		$primary = $this->db->getPrimary( $this->table );

		if ( is_array( $primary ) ) {

			$this->execute();
			$or = array();

			foreach ( $this->rows as $row ) {

				$and = array();

				foreach ( $primary as $column ) {

					$and[] = $this->db->is( $column, $row[ $column ] );

				}

				$or[] = "( " . implode( " AND ", $and ) . " )";

			}

			return $result->where( implode( " OR ", $or ) );

		}

		return $result->where( $primary, $this->getLocalKeys( $primary ) );

	}

	// Select

	/**
	 * Return a new result with an additional expression to the SELECT part
	 *
	 * @param string $expr
	 * @return Result
	 */
	function select( $expr ) {

		$clone = clone $this;

		if ( $clone->select === null ) {

			$clone->select = func_get_args();

		} else {

			$clone->select = array_merge( $clone->select, func_get_args() );

		}

		return $clone;

	}

	/**
	 * Add a WHERE condition (multiple are combined with AND)
	 *
	 * @param string|array $condition
	 * @param string|array $params
	 * @return Result
	 */
	function where( $condition, $params = array() ) {

		$clone = clone $this;

		// conditions in key-value array
		if ( is_array( $condition ) ) {

			foreach ( $condition as $c => $params ) {

				$clone = $clone->where( $c, $params );

			}

			return $clone;

		}

		// shortcut for basic "column is (in) value"
		if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {

			$clone->where[] = $clone->db->is( $condition, $params );

			return $clone;

		}

		if ( !is_array( $params ) ) {

			$params = func_get_args();
			array_shift( $params );

		}

		$clone->where[] = $condition;
		$clone->whereParams = array_merge( $clone->whereParams, $params );

		return $clone;

	}

	/**
	 * Add a "$column is not $value" condition to WHERE (multiple are combined with AND)
	 *
	 * @param string|array $column
	 * @param string|array|null $value
	 * @return $this
	 */
	function whereNot( $column, $value = null ) {

		$this->immutable();

		// conditions in key-value array
		if ( is_array( $column ) ) {

			foreach ( $column as $c => $params ) {

				$this->whereNot( $c, $params );

			}

			return $this;

		}

		$this->where[] = $this->db->isNot( $column, $value );

		return $this;

	}

	/**
	 * Add an ORDER BY column and direction
	 *
	 * @param string $column
	 * @param string $direction
	 * @return $this
	 */
	function orderBy( $column, $direction = "ASC" ) {

		$clone = clone $this;

		$clone->orderBy[] = $this->db->quoteIdentifier( $column ) . " " . $direction;

		return $clone;

	}

	/**
	 * Set a result limit and optionally an offset
	 *
	 * @param int $count
	 * @param int|null $offset
	 * @return $this
	 */
	function limit( $count, $offset = null ) {

		if ( $this->parent_ ) {

			throw new \LogicException( 'Cannot limit referenced result' );

		}

		$clone = clone $this;

		$clone->limitCount = $count;
		$clone->limitOffset = $offset;

		return $clone;

	}

	/**
	 * Set a paged limit
	 * Pages start at 1
	 *
	 * @param int $pageSize
	 * @param int $page
	 * @return $this
	 */
	function paged( $pageSize, $page ) {

		return $this->limit( $pageSize, ($page - 1) * $pageSize );

	}

	// Aggregate functions

	/**
	 * Count number of rows
	 * Implements Countable
	 *
	 * @param string $expr
	 * @return int
	 */
	function count( $expr = "*" ) {

		return (int) $this->aggregate( "COUNT(" . $expr . ")" );

	}

	/**
	 * Return minimum value from an expression
	 *
	 * @param string $expr
	 * @return string
	 */
	function min( $expr ) {

		return $this->aggregate( "MIN(" . $expr . ")" );

	}

	/**
	 * Return maximum value from an expression
	 *
	 * @param string $expr
	 * @return string
	 */
	function max( $expr ) {

		return $this->aggregate( "MAX(" . $expr . ")" );

	}

	/**
	 * Return sum of values in an expression
	 *
	 * @param string $expr
	 * @return string
	 */
	function sum( $expr ) {

		return $this->aggregate( "SUM(" . $expr . ")" );

	}

	/**
	 * Execute aggregate function and return value
	 *
	 * @param string $function
	 * @return mixed
	 */
	function aggregate( $function ) {

		if ( $this->parent_ ) {

			throw new \LogicException( 'Cannot aggregate referenced result' );

		}

		$statement = $this->db->select( $this->table, array(
			'expr' => $function,
			'where' => $this->where,
			'orderBy' => $this->orderBy,
			'limitCount' => $this->limitCount,
			'limitOffset' => $this->limitOffset,
			'params' => $this->whereParams
		) );

		foreach ( $statement->fetch() as $return ) {

			return $return;

		}

	}

	//

	/**
	 * IteratorAggregate
	 *
	 * @return \ArrayIterator
	 */
	function getIterator() {

		$this->execute();

		return new \ArrayIterator( $this->rows );

	}

	/**
	 * Get a JSON string defining the SELECT information of this Result
	 * Used as identification in caches
	 *
	 * @return string
	 */
	function getDefinition() {

		return json_encode( array(

			'table' => $this->table,
			'select' => $this->select,
			'where' => $this->where,
			'whereParams' => $this->whereParams,
			'orderBy' => $this->orderBy,
			'limitCount' => $this->limitCount,
			'limitOffset' => $this->limitOffset

		) );

	}

	/**
	 * Get parent result or row, if any
	 *
	 * @return Result|Row
	 */
	function getParent() {

		return $this->parent_;

	}

	//

	/**
	 *
	 */
	function __clone() {

		$this->rows = null;
		$this->globalRows = null;

	}

	//

	/**
	 * Implements JsonSerialize
	 *
	 * @return Row[]
	 */
	function jsonSerialize() {

		return $this->fetchAll();

	}

	// General members

	/** @var Database */
	protected $db;

	/** @var null|Row[] */
	protected $rows;

	/** @var null|Row[] */
	protected $globalRows;

	// Select information

	/** @var string */
	protected $table;

	/** @var null|string */
	protected $select;

	/** @var array */
	protected $where = array();

	/** @var array */
	protected $whereParams = array();

	/** @var array */
	protected $orderBy = array();

	/** @var null|int */
	protected $limitCount;

	/** @var null|int */
	protected $limitOffset;

	// Members for results representing associations

	/** @var null|Result|Row */
	protected $parent_;

	/** @var null|bool */
	protected $single;

	/** @var null|string */
	protected $key;

	/** @var null|string */
	protected $parentKey;

	// Root members

	/** @var array */
	protected $_cache = array();

}
