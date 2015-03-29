<?php

namespace LessQL;

/**
 * Represents a filtered table result.
 *
 *  SELECT
 *        {* | select_expr, ...}
 *        FROM table
 *            [WHERE condition [AND condition [...]]]
 *            [ORDER BY {col_name | expr | position} [ASC | DESC], ...]
 *            [LIMIT count [OFFSET offset]]
 *
 * TODO Add more SQL dialect specifics like FETCH FIRST, TOP etc.
 *
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
	 * @return Result|Row
	 */
	function referenced( $name, $where = null, $params = array() ) {

		$result = $this->db->createResult( $this, $name );

		if ( $where !== null ) {

			if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 2 );
			$result->where( $where, $params );

		}

		return $result;

	}

	/**
	 * Set reference key for this result
	 *
	 * @param string $key
	 * @return $this
	 */
	function via( $key ) {

		if ( !$this->parent_ ) throw new \LogicException( 'Cannot set reference key on basic Result' );

		if ( $this->single ) {

			$this->parentKey = $key;

		} else {

			$this->key = $key;

		}

		return $this;

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
			$this->where( $this->key, $this->parent_->getGlobalKeys( $this->parentKey ) );

		}

		$root = $this->getRoot();
		$definition = $this->getDefinition();

		$cached = $root->getCache( $definition );

		if ( !$cached ) {

			// fetch all rows
			$statement = $this->db->select(
				$this->table, $this->select, $this->where, $this->orderBy,
				$this->limitCount, $this->limitOffset, $this->whereParams
			);

			$rows = $statement->fetchAll();
			$cached = array();

			// build row objects
			foreach ( $rows as $row ) {

				$row = $this->db->createRow( $this->table, $row, $this );
				$row->setClean();

				$cached[ ] = $row;

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

					$this->rows[ ] = $row;

				}

			}

		}

		return $this;

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

		$keys = array();

		foreach ( $this->rows as $row ) {

			if ( isset( $row->{$key} ) ) {

				$keys[ ] = $row->{$key};

			}

		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Get global $key values of the result, i.e., disregarding its parent
	 *
	 * @param string $key
	 * @return array
	 */
	function getGlobalKeys( $key ) {

		$this->execute();

		$keys = array();

		foreach ( $this->globalRows as $row ) {

			if ( isset( $row->{$key} ) ) {

				$keys[ ] = $row->{$key};

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
	 */
	function setCache( $key, $value ) {

		$this->_cache[ $key ] = $value;

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

		$this->db->insert( $this->table, $rows, $method );

	}

	/**
	 * Update the rows matched by this result, setting $data
	 *
	 * @param array $data
	 * @return null|\PDOStatement
	 */
	function update( $data ) {

		// if this is a related result or it is limited,
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

		// if this is a related result or it is limited,
		// create specific result for local rows and execute

		if ( $this->parent_ || isset( $this->limitCount ) ) {

			return $this->primaryResult()->delete();

		}

		return $this->db->delete( $this->table, $this->where, $this->whereParams );

	}

	/**
	 * Return a new root result which selects all rows in this result by primary key
	 *
	 * @return $this
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

					$and[ ] = $this->db->is( $column, $row[ $column ] );

				}

				$or[ ] = "( " . implode( " AND ", $and ) . " )";

			}

			return $result->where( implode( " OR ", $or ) );

		}

		return $result->where( $primary, $this->getLocalKeys( $primary ) );

	}

	// Select

	/**
	 * Add an expression to the SELECT part
	 *
	 * @param string $expr
	 * @return $this
	 */
	function select( $expr ) {

		$this->immutable();

		if ( $this->select === null ) {

			$this->select = func_get_args();

		} else {

			$this->select = array_merge( $this->select, func_get_args() );

		}

		return $this;

	}

	/**
	 * Add a WHERE condition (multiple are combined with AND)
	 *
	 * @param string|array $condition
	 * @param string|array $params
	 * @return $this
	 */
	function where( $condition, $params = array() ) {

		$this->immutable();

		// conditions in key-value array
		if ( is_array( $condition ) ) {

			foreach ( $condition as $c => $params ) {

				$this->where( $c, $params );

			}

			return $this;

		}

		// shortcut for basic "column is (in) value"
		if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {

			$this->where[ ] = $this->db->is( $condition, $params );

			return $this;

		}

		if ( !is_array( $params ) ) {

			$params = func_get_args();
			array_shift( $params );

		}

		$this->where[ ] = $condition;
		$this->whereParams = array_merge( $this->whereParams, $params );

		return $this;

	}

	/**
	 * Add a "$column is not $value" condition to WHERE (multiple are combined with AND)
	 *
	 * @param string|array $column
	 * @param string|array $value
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

		$this->where[ ] = $this->db->isNot( $column, $value );

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

		$this->immutable();

		$this->orderBy[ ] = $this->db->quoteIdentifier( $column ) . " " . $direction;

		return $this;

	}

	/**
	 * Set a result limit and optionally an offset
	 *
	 * @param int $count
	 * @param int|null $offset
	 * @return $this
	 */
	function limit( $count, $offset = null ) {

		$this->immutable();

		if ( $this->parent_ ) {

			throw new \LogicException( 'Cannot limit referenced result' );

		}

		$this->limitCount = $count;
		$this->limitOffset = $offset;

		return $this;

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

		$this->limit( $pageSize, ( $page - 1 ) * $pageSize );

		return $this;

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

		return (int)$this->aggregate( "COUNT(" . $expr . ")" );

	}

	/**
	 * Return minimum value from a expression
	 *
	 * @param string $expr
	 * @return string
	 */
	function min( $expr ) {

		return $this->aggregate( "MIN(" . $expr . ")" );

	}

	/**
	 * Return maximum value from a expression
	 *
	 * @param string $expr
	 * @return string
	 */
	function max( $expr ) {

		return $this->aggregate( "MAX(" . $expr . ")" );

	}

	/**
	 * Return sum of values in a expression
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

		$statement = $this->db->select( $this->table, $function, $this->where, $this->orderBy,
			$this->limitCount, $this->limitOffset, $this->whereParams );

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

	//

	/**
	 * Throw exception if this Result has been already executed
	 *
	 * @throws \LogicException
	 */
	protected function immutable() {

		if ( isset( $this->rows ) ) {

			throw new \LogicException( 'Cannot modify Result after execution' );

		}

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

	/** @var array */
	protected $rows;

	/** @var array */
	protected $globalRows;


	// Select information

	/** @var string */
	public $table;

	/** @var string */
	public $select;

	/** @var array */
	public $where = array();

	/** @var array */
	public $whereParams = array();

	/** @var array */
	public $orderBy = array();

	/** @var int */
	public $limitCount;

	/** @var int */
	public $limitOffset;


	// Members for results representing associations

	/** @var Database|Result|Row */
	protected $parent_;

	/** @var bool */
	protected $single;

	/** @var string */
	protected $key;

	/** @var array|string */
	protected $parentKey;


	// Root members

	/** @var array */
	protected $_cache;

}
