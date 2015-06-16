<?php

namespace LessQL;

/**
 * Database object wrapping a PDO instance
 */
class Database {

	/**
	 * Constructor. Sets PDO to exception mode.
	 *
	 * @param \PDO $pdo
	 */
	function __construct( $pdo ) {

		// required for safety
		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
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
	 * @return Result|Row|null
	 */
	function __call( $name, $args ) {

		array_unshift( $args, $name );

		return call_user_func_array( array( $this, 'table' ), $args );

	}

	/**
	 * Returns a result for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * @param $name
	 * @param int|null $id
	 * @return Result|Row|null
	 */
	function table( $name, $id = null ) {

		// ignore List suffix
		$name = preg_replace( '/List$/', '', $name );

		if ( $id !== null ) {

			$result = $this->createResult( $this, $name );

			if ( !is_array( $id ) ) {

				$table = $this->getAlias( $name );
				$primary = $this->getPrimary( $table );
				$id = array( $primary => $id );

			}

			return $result->where( $id )->fetch();

		}

		return $this->createResult( $this, $name );

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
	function createRow( $name, $properties = array(), $result = null ) {

		return new Row( $this, $name, $properties, $result );

	}

	/**
	 * Create a result bound to $parent using table or association $name.
	 * $parent may be the database, a result, or a row
	 *
	 * @param Database|Result|Row $parent
	 * @param string $name
	 * @return Result
	 */
	function createResult( $parent, $name ) {

		return new Result( $parent, $name );

	}

	// PDO interface

	/**
	 * Prepare an SQL statement
	 *
	 * @param string $query
	 * @return \PDOStatement
	 */
	function prepare( $query ) {

		return $this->pdo->prepare( $query );

	}

	/**
	 * Return last inserted id
	 *
	 * @param string|null $sequence
	 * @return string
	 */
	function lastInsertId( $sequence = null ) {

		return $this->pdo->lastInsertId( $sequence );

	}

	/**
	 * Begin a transaction
	 *
	 * @return bool
	 */
	function begin() {

		return $this->pdo->beginTransaction();

	}

	/**
	 * Commit changes of transaction
	 *
	 * @return bool
	 */
	function commit() {

		return $this->pdo->commit();

	}

	/**
	 * Rollback any changes during transaction
	 *
	 * @return bool
	 */
	function rollback() {

		return $this->pdo->rollBack();

	}

	// Schema hints

	/**
	 * Get primary key of a table, may be array for compound keys
	 *
	 * Convention is "id"
	 *
	 * @param string $table
	 * @return string|array
	 */
	function getPrimary( $table ) {

		if ( isset( $this->primary[ $table ] ) ) {

			return $this->primary[ $table ];

		}

		return 'id';

	}

	/**
	 * Set primary key of a table.
	 * Compound keys may be passed as an array.
	 * Always set compound primary keys explicitly with this method.
	 *
	 * @param string $table
	 * @param string|array $key
	 * @return $this
	 */
	function setPrimary( $table, $key ) {

		$this->primary[ $table ] = $key;

		// compound keys are never auto-generated,
		// so we can assume they are required
		if ( is_array( $key ) ) {

			foreach ( $key as $k ) {

				$this->setRequired( $table, $k );

			}

		}

		return $this;

	}

	/**
	 * Get a reference key for an association on a table
	 *
	 * "How would $table reference another table under $name?"
	 *
	 * Convention is "$name_id"
	 *
	 * @param string $table
	 * @param string $name
	 * @return string
	 */
	function getReference( $table, $name ) {

		if ( isset( $this->references[ $table ][ $name ] ) ) {

			return $this->references[ $table ][ $name ];

		}

		return $name . '_id';

	}

	/**
	 * Set a reference key for an association on a table
	 *
	 * @param string $table
	 * @param string $name
	 * @param string $key
	 * @return $this
	 */
	function setReference( $table, $name, $key ) {

		$this->references[ $table ][ $name ] = $key;

		return $this;

	}

	/**
	 * Get a back reference key for an association on a table
	 *
	 * "How would $table be referenced by another table under $name?"
	 *
	 * Convention is "$table_id"
	 *
	 * @param string $table
	 * @param string $name
	 * @return string
	 */
	function getBackReference( $table, $name ) {

		if ( isset( $this->backReferences[ $table ][ $name ] ) ) {

			return $this->backReferences[ $table ][ $name ];

		}

		return $table . '_id';

	}

	/**
	 * Set a back reference key for an association on a table
	 *
	 * @param string $table
	 * @param string $name
	 * @param string $key
	 * @return $this
	 */
	function setBackReference( $table, $name, $key ) {

		$this->backReferences[ $table ][ $name ] = $key;

		return $this;

	}

	/**
	 * Get alias of a table
	 *
	 * @param string $alias
	 * @return string
	 */
	function getAlias( $alias ) {

		return isset( $this->aliases[ $alias ] ) ? $this->aliases[ $alias ] : $alias;

	}

	/**
	 * Set alias of a table
	 *
	 * @param string $alias
	 * @param string $table
	 * @return $this
	 */
	function setAlias( $alias, $table ) {

		$this->aliases[ $alias ] = $table;

		return $this;

	}

	/**
	 * Is a column of a table required for saving? Default is no
	 *
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	function isRequired( $table, $column ) {

		return isset( $this->required[ $table ][ $column ] );

	}

	/**
	 * Get a map of required columns of a table
	 *
	 * @param string $table
	 * @return array
	 */
	function getRequired( $table ) {

		return isset( $this->required[ $table ] ) ? $this->required[ $table ] : array();

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
	function setRequired( $table, $column ) {

		$this->required[ $table ][ $column ] = true;

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
	function getSequence( $table ) {

		if ( isset( $this->sequences[ $table ] ) ) {

			return $this->sequences[ $table ];

		}

		$primary = $this->getPrimary( $table );

		if ( is_array( $primary ) ) return null;

		$table = $this->rewriteTable( $table );

		return $table . '_' . $primary . '_seq';

	}

	/**
	 * Set primary sequence name of table
	 *
	 * @param string $table
	 * @param string $sequence
	 * @return $this
	 */
	function setSequence( $table, $sequence ) {

		$this->sequences[ $table ] = $sequence;

		return $this;

	}

	/**
	 * Get rewritten table name
	 *
	 * @param string $table
	 * @return string
	 */
	function rewriteTable( $table ) {

		if ( is_callable( $this->rewrite ) ) {

			return call_user_func( $this->rewrite, $table );

		}

		return $table;

	}

	/**
	 * Set table rewrite function
	 * For example, it could add a prefix
	 *
	 * @param callable $rewrite
	 * @return $this
	 */
	function setRewrite( $rewrite ) {

		$this->rewrite = $rewrite;

		return $this;

	}

	// SQL style

	/**
	 * Get identifier delimiter
	 *
	 * @return string
	 */
	function getIdentifierDelimiter() {

		return $this->identifierDelimiter;

	}

	/**
	 * Sets delimiter used when quoting identifiers.
	 * Should be backtick or double quote.
	 * Set to null to disable quoting.
	 *
	 * @param string|null $d
	 * @return $this
	 */
	function setIdentifierDelimiter( $d ) {

		$this->identifierDelimiter = $d;

		return $this;

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
	function select( $table, $options = array() ) {

		$options = array_merge( array(

			'expr' => null,
			'where' => array(),
			'orderBy' => array(),
			'limitCount' => null,
			'limitOffset' => null,
			'params' => array()

		), $options );

		$query = "SELECT ";

		if ( empty( $options[ 'expr' ] ) ) {

			$query .= "*";

		} else if ( is_array( $options[ 'expr' ] ) ) {

			$query .= implode( ", ", $options[ 'expr' ] );

		} else {

			$query .= $options[ 'expr' ];

		}

		$table = $this->rewriteTable( $table );
		$query .= " FROM " . $this->quoteIdentifier( $table );

		$query .= $this->getSuffix( $options[ 'where' ], $options[ 'orderBy' ], $options[ 'limitCount' ], $options[ 'limitOffset' ] );

		$this->onQuery( $query, $options[ 'params' ] );

		$statement = $this->prepare( $query );
		$statement->setFetchMode( \PDO::FETCH_ASSOC );
		$statement->execute( $options[ 'params' ] );

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
	function insert( $table, $rows, $method = null ) {

		if ( empty( $rows ) ) return;
		if ( !isset( $rows[ 0 ] ) ) $rows = array( $rows );

		if ( $method === 'prepared' ) {

			return $this->insertPrepared( $table, $rows );

		} else if ( $method === 'batch' ) {

			return $this->insertBatch( $table, $rows );

		} else {

			return $this->insertDefault( $table, $rows );

		}

	}

	/**
	 * Insert rows using a prepared query
	 *
	 * @param string $table
	 * @param array $rows
	 * @return \PDOStatement|null
	 */
	protected function insertPrepared( $table, $rows ) {

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$query = $this->insertHead( $table, $columns );
		$query .= "( ?" . str_repeat( ", ?", count( $columns ) - 1 ) . " )";

		$statement = $this->prepare( $query );

		foreach ( $rows as $row ) {

			$values = array();

			foreach ( $columns as $column ) {

				$value = (string) $this->format( @$row[ $column ] );
				$values[] = $value;

			}

			$this->onQuery( $query, $values );

			$statement->execute( $values );

		}

		return $statement;

	}

	/**
	 * Insert rows using a single batch query
	 *
	 * @param string $table
	 * @param array $rows
	 * @return \PDOStatement|null
	 */
	protected function insertBatch( $table, $rows ) {

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$query = $this->insertHead( $table, $columns );
		$lists = $this->valueLists( $rows, $columns );
		$query .= implode( ", ", $lists );

		$this->onQuery( $query );

		$statement = $this->prepare( $query );
		$statement->execute();

		return $statement;

	}

	/**
	 * Insert rows using one query per row
	 *
	 * @param string $table
	 * @param array $rows
	 * @return \PDOStatement|null
	 */
	protected function insertDefault( $table, $rows ) {

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$query = $this->insertHead( $table, $columns );
		$lists = $this->valueLists( $rows, $columns );

		foreach ( $lists as $list ) {

			$singleQuery = $query . $list;

			$this->onQuery( $singleQuery );

			$statement = $this->prepare( $singleQuery );
			$statement->execute();

		}

		return $statement; // last statement is returned

	}

	/**
	 * Build head of INSERT query (without values)
	 *
	 * @param string $table
	 * @param array $columns
	 * @return string
	 */
	protected function insertHead( $table, $columns ) {

		$quotedColumns = array_map( array( $this, 'quoteIdentifier' ), $columns );
		$table = $this->rewriteTable( $table );
		$query = "INSERT INTO " . $this->quoteIdentifier( $table );
		$query .= " ( " . implode( ", ", $quotedColumns ) . " ) VALUES ";

		return $query;

	}

	/**
	 * Get list of all columns used in the given rows
	 *
	 * @param array $rows
	 * @return array
	 */
	protected function getColumns( $rows ) {

		$columns = array();

		foreach ( $rows as $row ) {

			foreach ( $row as $column => $value ) {

				$columns[ $column ] = true;

			}

		}

		return array_keys( $columns );

	}

	/**
	 * Build lists of quoted values for INSERT
	 *
	 * @param array $rows
	 * @param array $columns
	 * @return array
	 */
	protected function valueLists( $rows, $columns ) {

		$lists = array();

		foreach ( $rows as $row ) {

			$values = array();

			foreach ( $columns as $column ) {

				$values[] = $this->quote( @$row[ $column ] );

			}

			$lists[] = "( " . implode( ", ", $values ) . " )";

		}

		return $lists;

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
	function update( $table, $data, $where = array(), $params = array() ) {

		if ( empty( $data ) ) return;

		$set = array();

		foreach ( $data as $column => $value ) {

			$set[] = $this->quoteIdentifier( $column ) . " = " . $this->quote( $value );

		}

		if ( !is_array( $where ) ) $where = array( $where );
		if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 3 );

		$table = $this->rewriteTable( $table );
		$query = "UPDATE " . $this->quoteIdentifier( $table );
		$query .= " SET " . implode( ", ", $set );
		$query .= $this->getSuffix( $where );

		$this->onQuery( $query, $params );

		$statement = $this->prepare( $query );
		$statement->execute( $params );

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
	function delete( $table, $where = array(), $params = array() ) {

		if ( !is_array( $where ) ) $where = array( $where );
		if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 2 );

		$table = $this->rewriteTable( $table );
		$query = "DELETE FROM " . $this->quoteIdentifier( $table );
		$query .= $this->getSuffix( $where );

		$this->onQuery( $query, $params );

		$statement = $this->prepare( $query );
		$statement->execute( $params );

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
	function getSuffix( $where, $orderBy = array(), $limitCount = null, $limitOffset = null ) {

		$suffix = "";

		if ( !empty( $where ) ) {

			$suffix .= " WHERE " . implode( " AND ", $where );

		}

		if ( !empty( $orderBy ) ) {

			$suffix .= " ORDER BY " . implode( ", ", $orderBy );

		}

		if ( isset( $limitCount ) ) {

			$suffix .= " LIMIT " . intval( $limitCount );

			if ( isset( $limitOffset ) ) {

				$suffix .= " OFFSET " . intval( $limitOffset );

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
	function is( $column, $value, $not = false ) {

		$bang = $not ? "!" : "";
		$or = $not ? " AND " : " OR ";
		$novalue = $not ? "1=1" : "0=1";
		$not = $not ? " NOT" : "";

		// always treat value as array
		if ( !is_array( $value ) ) {

			$value = array( $value );

		}

		// always quote column identifier
		$column = $this->quoteIdentifier( $column );

		if ( count( $value ) === 1 ) {

			// use single column comparison if count is 1

			$value = $value[ 0 ];

			if ( $value === null ) {

				return $column . " IS" . $not . " NULL";

			} else {

				return $column . " " . $bang . "= " . $this->quote( $value );
			}

		} else if ( count( $value ) > 1 ) {

			// if we have multiple values, use IN clause

			$values = array();
			$null = false;

			foreach ( $value as $v ) {

				if ( $v === null ) {

					$null = true;

				} else {

					$values[] = $this->quote( $v );

				}

			}

			$clauses = array();

			if ( !empty( $values ) ) {

				$clauses[] = $column . $not . " IN ( " . implode( ", ", $values ) . " )";

			}

			if ( $null ) {

				$clauses[] = $column . " IS" . $not . " NULL";

			}

			return implode( $or, $clauses );

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
	function isNot( $column, $value ) {

		return $this->is( $column, $value, true );

	}

	/**
	 * Quote a value for SQL
	 *
	 * @param mixed $value
	 * @return string
	 */
	function quote( $value ) {

		$value = $this->format( $value );

		if ( $value === null ) {

			return "NULL";

		}

		if ( $value === false ) {

			return "'0'";

		}

		if ( $value === true ) {

			return "'1'";

		}

		if ( is_int( $value ) ) {

			return "'" . ( (string) $value ) . "'";

		}

		if ( is_float( $value ) ) {

			return "'" . sprintf( "%F", $value ) . "'";

		}

		if ( $value instanceof Literal ) {

			return $value->value;

		}

		return $this->pdo->quote( $value );

	}

	/**
	 * Format a value for SQL, e.g. DateTime objects
	 *
	 * @param mixed $value
	 * @return string
	 */
	function format( $value ) {

		if ( $value instanceof \DateTime ) {

			return $value->format( "Y-m-d H:i:s" );

		}

		return $value;

	}

	/**
	 * Quote identifier
	 *
	 * @param string $identifier
	 * @return string
	 */
	function quoteIdentifier( $identifier ) {

		$delimiter = $this->identifierDelimiter;

		if ( empty( $delimiter ) ) return $identifier;

		$identifier = explode( ".", $identifier );

		$identifier = array_map(
			function( $part ) use ( $delimiter ) { return $delimiter . str_replace( $delimiter, $delimiter.$delimiter, $part ) . $delimiter; },
			$identifier
		);

		return implode( ".", $identifier );

	}

	/**
	 * Create a SQL Literal
	 *
	 * @param string $value
	 * @return Literal
	 */
	function literal( $value ) {

		return new Literal( $value );

	}

	//

	/**
	 * Calls the query callback, if any
	 *
	 * @param string $query
	 * @param array $params
	 */
	function onQuery( $query, $params = array() ) {

		if ( is_callable( $this->queryCallback ) ) {

			call_user_func( $this->queryCallback, $query, $params );

		}

	}

	/**
	 * Set the query callback
	 *
	 * @param callable $callback
	 * @return $this
	 */
	function setQueryCallback( $callback ) {

		$this->queryCallback = $callback;

		return $this;

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

	/** @var null|callable */
	protected $rewrite;

	//

	/** @var null|callable */
	protected $queryCallback;

}
