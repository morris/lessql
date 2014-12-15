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
 *
 */
class Result implements \IteratorAggregate, \JsonSerializable {

	/**
	 * Constructor
	 * Use $db->createResult( $parent, $name ) instead
	 */
	function __construct( $parent, $name ) {

		if ( $parent instanceof Database ) {

			// basic result

			$this->db = $parent;
			$this->table = $this->db->getAlias( $name );

		} else { // row or result

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
	 */
	function __call( $name, $args ) {

		array_unshift( $args, $name );

		return call_user_func_array( array( $this, 'referenced' ), $args );

	}

	/**
	 * Get referenced row(s) by name. Suffix "List" gets many rows
	 */
	function referenced( $name, $where = null, $params = array() ) {

		$result = $this->db->createResult( $this, $name );

		if ( $where !== null ) $result->where( $where, $params );

		return $result;

	}

	/**
	 * Set reference key for this result
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
			$query = $this->getQuery();
			$params = $this->whereParams;

			$this->db->onQuery( $query, $params );

			$statement = $this->db->prepare( $query );
			$statement->setFetchMode( \PDO::FETCH_ASSOC );
			$statement->execute( $params );

			$rows = $statement->fetchAll();
			$cached = array();

			// build row objects
			foreach ( $rows as $row ) {

				$row = $this->db->createRow( $this->table, $row, $this );
				$row->clean();

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
	 * Get the database
	 */
	function getDatabase() {

		return $this->db;

	}

	/**
	 * Get the root result
	 */
	function getRoot() {

		if ( !$this->parent_ ) {

			return $this;

		}

		return $this->parent_->getRoot();

	}

	/**
	 * Get the table of this result
	 */
	function getTable() {

		return $this->table;

	}

	/**
	 * Get $key values of this result
	 */
	function getLocalKeys( $key ) {

		$this->execute();

		$keys = array();

		foreach ( $this->rows as $row ) {

			if ( isset( $row->{ $key } ) ) {

				$keys[] = $row->{ $key };

			}

		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Get global $key values of the result, i.e., disregarding its parent
	 */
	function getGlobalKeys( $key ) {

		$this->execute();

		$keys = array();

		foreach ( $this->globalRows as $row ) {

			if ( isset( $row->{ $key } ) ) {

				$keys[] = $row->{ $key };

			}

		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Get value from cache
	 */
	function getCache( $key ) {

		return isset( $this->_cache[ $key ] ) ? $this->_cache[ $key ] : null;

	}

	/**
	 * Set cache value
	 */
	function setCache( $key, $value ) {

		$this->_cache[ $key ] = $value;

	}

	/**
	 * Is this result a single association, i.e. not a list of rows?
	 */
	function isSingle() {

		return $this->single;

	}

	/**
	 * Fetch the next row in this result
	 */
	function fetch() {

		$this->execute();

		list( $index, $row ) = each( $this->rows );

		return $row;

	}

	/**
	 * Fetch all rows in this result
	 */
	function fetchAll() {

		$this->execute();

		return $this->rows;

	}

	/**
	 * Return number of rows in this result
	 */
	function rowCount() {

		$this->execute();

		return count( $this->rows );

	}

	// Manipulation

	/**
	 * Insert rows into the table of this result
	 */
	function insert( $rows ) {

		if ( empty( $rows ) ) return;

		if ( !isset( $rows[ 0 ] ) ) {

			$rows = array( $rows );

		}

		$columns = array();

		foreach ( $rows[ 0 ] as $column => $value ) {

			$columns[] = $column;

		}

		$lists = array();

		foreach ( $rows as $row ) {

			$values = array();

			foreach ( $columns as $column ) {

				$values[] = $this->db->quote( $row[ $column ] );

			}

			$lists[] = "( " . implode( ", ", $values ) . " )";

		}

		$columns = array_map( array( $this->db, 'quoteIdentifier' ), $columns );

		$query = "INSERT INTO " . $this->db->quoteIdentifier( $this->table );
		$query .= " ( " . implode( ", ", $columns ) . " )";
		$query .= " VALUES " . implode( ", ", $lists );

		$this->db->onQuery( $query );

		$statement = $this->db->prepare( $query );
		$statement->execute();

		return $statement;

	}

	/**
	 * Update the rows matched by this result, setting $data
	 */
	function update( $data ) {

		if ( empty( $data ) ) return;

		$set = array();

		foreach ( $data as $field => $value ) {

			$set[] = $this->db->quoteIdentifier( $field ) . " = " . $this->db->quote( $value );

		}

		$query = "UPDATE " . $this->db->quoteIdentifier( $this->table );
		$query .= " SET " . implode( ", ", $set );

		if ( !empty( $this->where ) ) {

			$query .= " WHERE " . implode( " AND ", $this->where );

		}

		$params = $this->whereParams;

		$this->db->onQuery( $query );

		$statement = $this->db->prepare( $query );
		$statement->execute( $params );

		return $statement;

	}

	/**
	 * Delete all rows matched by this result
	 */
	function delete() {

		$query = "DELETE FROM " . $this->db->quoteIdentifier( $this->table );

		if ( !empty( $this->where ) ) {

			$query .= " WHERE " . implode( " AND ", $this->where );

		}

		$params = $this->whereParams;

		$this->db->onQuery( $query );

		$statement = $this->db->prepare( $query );
		$statement->execute( $params );

		return $statement;

	}

	// Select

	/**
	 * Add an expression to the SELECT part
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

			$this->where[] = $this->db->is( $condition, $params );

			return $this;

		}

		if ( !is_array( $params ) ) {

			$params = func_get_args();
			array_shift( $params );

		}

		$this->where[] = $condition;
		$this->whereParams = array_merge( $this->whereParams, $params );

		return $this;

	}

	/**
	 * Add a "$column is not $value" condition to WHERE (multiple are combined with AND)
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

	}

	/**
	 * Add an ORDER BY column and direction
	 */
	function orderBy( $column, $direction = "ASC" ) {

		$this->immutable();

		$this->orderBy[] = $this->db->quoteIdentifier( $column ) . " " . $direction;

		return $this;

	}

	/**
	 * Set a result limit and optionally an offset
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
	 */
	function paged( $pageSize, $page ) {

		$this->limit( $pageSize, ($page - 1) * $pageSize );

		return $this;

	}

	//

	/**
	 * Build the SELECT query defined by this result
	 */
	function getQuery() {

		$query = "SELECT ";

		if ( empty( $this->select ) ) {

			$query .= "*";

		} else {

			$query .= implode( ", ", $this->select );

		}

		$query .= " FROM " . $this->db->quoteIdentifier( $this->table );

		if ( !empty( $this->joins ) ) {

			$query .= " " . $this->implode( " ", $this->joins );

		}

		if ( !empty( $this->where ) ) {

			$query .= " WHERE " . implode( " AND ", $this->where );

		}

		if ( !empty( $this->orderBy ) )  {

			$query .= " ORDER BY " . implode( ", ", $this->orderBy );

		}

		if ( isset( $this->limitCount ) ) {

			$query .= " LIMIT :limitCount";

			if ( isset( $this->limitOffset ) ) {

				$query .= " OFFSET :limitOffset";

			}

		}

		return $query;

	}

	// Aggregate functions

	/**
	 * Count number of rows
	 * Implements Countable
	 */
	function count( $expr = "*" ) {

		return $this->aggregate( "COUNT(" . $expr . ")" );

	}

	/**
	 * Return minimum value from a expression
	 */
	function min( $expr ) {

		return $this->aggregate( "MIN(" . $expr . ")" );

	}

	/**
	 * Return maximum value from a expression
	 */
	function max( $expr ) {

		return $this->aggregate( "MAX(" . $expr . ")" );

	}

	/**
	 * Return sum of values in a expression
	 */
	function sum( $expr ) {

		return $this->aggregate( "SUM(" . $expr . ")" );

	}

	/**
	 * Execute aggregate function and return value
	 */
	function aggregate( $function ) {

		if ( $this->parent_ ) {

			throw new \LogicException( 'Cannot aggregate referenced result' );

		}

		$select = $this->select;
		$this->select = array( $function );

		$query = $this->getQuery();
		$params = $this->whereParams;

		$this->select = $select;

		$this->db->onQuery( $query, $params );

		$statement = $this->db->prepare( $query );
		$statement->execute( $params );

		foreach ( $statement->fetch() as $return ) {

			return $return;

		}

	}

	//

	/**
	 * IteratorAggregate
	 * @return \ArrayIterator
	 */
	function getIterator() {

		$this->execute();

		return new \ArrayIterator( $this->rows );

	}

	/**
	 * Get a JSON string defining the SELECT information of this Result
	 * Used as identification in caches
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
	 */
	protected function immutable() {

		if ( isset( $this->rows ) ) {

			throw new \LogicException( 'Cannot modify Result after execution' );

		}

	}

	//

	/**
	 * Implements JsonSerialize
	 */
	function jsonSerialize() {

		return $this->fetchAll();

	}

	// General members

	protected $db;

	protected $rows;

	protected $globalRows;


	// Select information

	public $table;

	public $select;

	public $where = array();

	public $whereParams = array();

	public $orderBy = array();

	public $limitCount;

	public $limitOffset;


	// Members for results representing associations

	protected $parent_;

	protected $single;

	protected $key;

	protected $parentKey;


	// Root members

	protected $_cache;

}
