<?php

namespace LessQL;

/**
 * Base object wrapping a PDO instance
 */
class Database {

	/**
	 * Constructor. Sets PDO to exception.
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
	 */
	function __call( $name, $args ) {

		array_unshift( $args, $name );

		return call_user_func_array( array( $this, 'table' ), $args );

	}

	/**
	 * Returns a result for table $name.
	 * If $id is given, return the row with that id.
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
	 */
	function createRow( $name, $properties = array(), $result = null ) {

		return new Row( $this, $name, $properties, $result );

	}

	/**
	 * Create a result bound to $parent using table or association $name.
	 * $parent may be the database, a result, or a row
	 */
	function createResult( $parent, $name ) {

		return new Result( $parent, $name );

	}

	// PDO interface

	/**
	 * Prepare an SQL statement
	 */
	function prepare( $query ) {

		return $this->pdo->prepare( $query );

	}

	/**
	 * Return last inserted id
	 */
	function lastInsertId( $sequence = null ) {

		return $this->pdo->lastInsertId( $sequence );

	}

	/**
	 * Begin a transaction
	 */
	function begin() {

		return $this->pdo->beginTransaction();

	}

	/**
	 * Commit changes of transaction
	 */
	function commit() {

		return $this->pdo->commit();

	}

	/**
	 * Rollback any changes during transaction
	 */
	function rollback() {

		return $this->pdo->rollBack();

	}

	// Schema hints

	/**
	 * Get primary key of a table, may be array for compound keys
	 *
	 * Convention is "id"
	 */
	function getPrimary( $table ) {

		if ( isset( $this->primary[ $table ] ) ) {

			return $this->primary[ $table ];

		}

		return 'id';

	}

	/**
	 * Set primary key of a table, may be array for compound keys.
	 * Always set it for tables with compound primary keys.
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
	 * Get a reference key for a association on a table
	 *
	 * "How would $table reference another table under $name?"
	 *
	 * Convention is "$name_id"
	 */
	function getReference( $table, $name ) {

		if ( isset( $this->references[ $table ][ $name ] ) ) {

			return $this->references[ $table ][ $name ];

		}

		return $name . '_id';

	}

	/**
	 * Set a reference key for a association on a table
	 */
	function setReference( $table, $name, $key ) {

		$this->references[ $table ][ $name ] = $key;

		return $this;

	}

	/**
	 * Get a back reference key for a association on a table
	 *
	 * "How would $table be referenced by another table under $name?"
	 *
	 * Convention is "$table_id"
	 */
	function getBackReference( $table, $name ) {

		if ( isset( $this->backReferences[ $table ][ $name ] ) ) {

			return $this->backReferences[ $table ][ $name ];

		}

		return $table . '_id';

	}

	/**
	 * Set a back reference key for a association on a table
	 */
	function setBackReference( $table, $name, $key ) {

		$this->backReferences[ $table ][ $name ] = $key;

		return $this;

	}

	/**
	 * Get alias of a table
	 */
	function getAlias( $alias ) {

		return isset( $this->aliases[ $alias ] ) ? $this->aliases[ $alias ] : $alias;

	}

	/**
	 * Set alias of a table
	 */
	function setAlias( $alias, $table ) {

		$this->aliases[ $alias ] = $table;

		return $this;

	}

	/**
	 * Is a column of a table required for saving? Default is no
	 */
	function isRequired( $table, $column ) {

		return isset( $this->required[ $table ][ $column ] );

	}

	/**
	 * Get a map of required columns of a table
	 */
	function getRequired( $table ) {

		return isset( $this->required[ $table ] ) ? $this->required[ $table ] : array();

	}

	/**
	 * Set a column to be required for saving
	 * Any primary key that is not auto-generated should be required
	 * Compound primary keys are required by default
	 */
	function setRequired( $table, $column ) {

		$this->required[ $table ][ $column ] = true;

		return $this;

	}

	/**
	 * Get primary sequence name of table (used in INSERT by Postgres)
	 *
	 * Conventions is "$tableRewritten_$primary_seq"
	 */
	function getSequence( $table ) {

		if ( isset( $this->sequences[ $table ] ) ) {

			return $this->sequences[ $table ];

		}

		$primary = $this->getPrimary( $table );
		$table = $this->rewriteTable( $table );

		if ( is_array( $primary ) ) return null;

		return $table . '_' . $primary . '_seq';

	}

	/**
	 * Set primary sequence name of table
	 */
	function setSequence( $table, $sequence ) {

		$this->sequences[ $table ] = $sequence;

	}

	/**
	 * Get rewritten table name
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
	 */
	function setRewrite( $rewrite ) {

		$this->rewrite = $rewrite;

	}

	// SQL style

	/**
	 * Get identifier delimiter
	 */
	function getIdentifierDelimiter() {

		return $this->identifierDelimiter;

	}

	/**
	 * Sets delimiter used when quoting identifiers. Should be backtick
	 * or double quote. Set to null to disable quoting.
	 */
	function setIdentifierDelimiter( $d ) {

		$this->identifierDelimiter = $d;

	}

	// SQL utility

	/**
	 * Build an SQL condition expressing that "$column is $value",
	 * or "$column is in $value" if $value is an array. Handles null
	 * and literals like new Literal( "NOW()" ) correctly.
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

				return $column . " " . $bang . "= ".$this->quote( $value );
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
	 */
	function isNot( $column, $value ) {

		return $this->is( $column, $value, true );

	}

	/**
	 * Quote a value for SQL
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
	 */
	function format( $value ) {

		if ( $value instanceof \DateTime ) {

			return $value->format( "Y-m-d H:i:s" );

		}

		return $value;

	}

	/**
	 * Quote identifier
	 */
	function quoteIdentifier( $identifier ) {

		$d = $this->identifierDelimiter;

		if ( empty( $d ) ) return $identifier;

		$identifier = explode( ".", $identifier );

		$identifier = array_map(
			function( $part ) use ( $d ) { return $d . str_replace( $d, $d.$d, $part ) . $d; },
			$identifier
		);

		return implode( ".", $identifier );

	}

	/**
	 * Create a SQL Literal
	 */
	function literal( $value ) {

		return new Literal( $value );

	}

	//

	/**
	 * Calls the query callback, if any
	 */
	function onQuery( $query, $params = array() ) {

		if ( is_callable( $this->queryCallback ) ) {

			call_user_func( $this->queryCallback, $query, $params );

		}

	}

	/**
	 * Set the query callback
	 */
	function setQueryCallback( $callback ) {

		$this->queryCallback = $callback;

	}

	//

	protected $identifierDelimiter = "`";

	//

	protected $primary = array();

	protected $references = array();

	protected $backReferences = array();

	protected $aliases = array();

	protected $required = array();

	protected $sequences = array();

	protected $rewrite;

	//

	protected $queryCallback;

}
