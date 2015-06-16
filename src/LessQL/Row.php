<?php

namespace LessQL;

/**
 * Represents a row of an SQL table (associative)
 */
class Row implements \ArrayAccess, \IteratorAggregate, \JsonSerializable {

	/**
	 * Constructor
	 * Use $db->createRow() instead
	 *
	 * @param Database $db
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 */
	function __construct( $db, $name, $properties = array(), $result = null ) {

		$this->_db = $db;
		$this->_result = $result;
		$this->_table = $this->_db->getAlias( $name );

		$this->setData( $properties );

	}

	/**
	 * Get a property
	 *
	 * @param string $column
	 * @return mixed
	 */
	function &__get( $column ) {

		if ( !isset( $this->_properties[ $column ] ) ) {

			$null = null;
			return $null;

		}

		return $this->_properties[ $column ];

	}

	/**
	 * Set a property
	 *
	 * @param string $column
	 * @param mixed $value
	 */
	function __set( $column, $value ) {

		if ( isset( $this->_properties[ $column ] ) && $this->_properties[ $column ] === $value ) {

			return;

		}

		// convert arrays to Rows or list of Rows

		if ( is_array( $value ) ) {

			$name = preg_replace( '/List$/', '', $column );
			$table = $this->getDatabase()->getAlias( $name );

			if ( $name === $column ) { // row

				$value = $this->getDatabase()->createRow( $table, $value );

			} else { // list

				foreach ( $value as $i => $v ) {

					$value[ $i ] = $this->getDatabase()->createRow( $table, $v );

				}

			}

		}

		$this->_properties[ $column ] = $value;
		$this->_modified[ $column ] = $value;

	}

	/**
	 * Check if property is not null
	 *
	 * @param string $column
	 * @return bool
	 */
	function __isset( $column ) {

		return isset( $this->_properties[ $column ] );

	}

	/**
	 * Remove a property from this row
	 * Property will be ignored when saved, different to setting to null
	 *
	 * @param string $column
	 * @return void
	 */
	function __unset( $column ) {

		unset( $this->_properties[ $column ] );
		unset( $this->_modified[ $column ] );

	}

	/**
	 * Get referenced row(s) by name. Suffix "List" gets many rows using
	 * a back reference.
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
	 * Get referenced row(s) by name. Suffix "List" gets many rows using
	 * a back reference.
	 *
	 * @param string $name
	 * @param string|array|null $where
	 * @param array $params
	 * @return Result
	 */
	function referenced( $name, $where = null, $params = array() ) {

		$result = $this->getDatabase()->createResult( $this, $name );

		if ( $where !== null ) {

			if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 2 );
			$result = $result->where( $where, $params );

		}

		return $result;

	}

	/**
	 * Get the row's id
	 *
	 * @return string|array
	 */
	function getId() {

		$primary = $this->getDatabase()->getPrimary( $this->getTable() );

		if ( is_array( $primary ) ) {

			$id = array();

			foreach ( $primary as $column ) {

				if ( !isset( $this[ $column ] ) ) return null;

				$id[ $column ] = $this[ $column ];

			}

			return $id;

		}

		return $this[ $primary ];

	}

	/**
	 * Get row data
	 *
	 * @return array
	 */
	function getData() {

		$data = array();

		foreach ( $this->_properties as $column => $value ) {

			if ( $value instanceof Row || is_array( $value ) ) {

				continue;

			}

			$data[ $column ] = $value;

		}

		return $data;

	}

	/**
	 * Set row data (extends the row)
	 *
	 * @param array $data
	 * @return $this
	 */
	function setData( $data ) {

		foreach ( $data as $column => $value ) {

			$this->__set( $column, $value );

		}

		return $this;

	}

	/**
	 * Get the original id
	 *
	 * @return string|array
	 */
	function getOriginalId() {

		return $this->_originalId;

	}

	/**
	 * Get modified data
	 *
	 * @return array
	 */
	function getModified() {

		$modified = array();

		foreach ( $this->_modified as $column => $value ) {

			if ( $value instanceof Row || is_array( $value ) ) {

				continue;

			}

			$modified[ $column ] = $value;

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
	function save( $recursive = true ) {

		$db = $this->getDatabase();
		$table = $this->getTable();

		if ( !$recursive ) { // just save the row

			$this->updateReferences();

			if ( !$this->isClean() ) {

				$primary = $db->getPrimary( $table );

				if ( $this->exists() ) {

					$idCondition = $this->getOriginalId();

					if ( !is_array( $idCondition ) ) {

						$idCondition = array( $primary => $idCondition );

					}

					$db->table( $table )
						->where( $idCondition )
						->update( $this->getModified() );

					$this->setClean();

				} else {

					$db->table( $table )
						->insert( $this->getData() );

					if ( !is_array( $primary ) && !isset( $this[ $primary ] ) ) {

						$id = $db->lastInsertId( $db->getSequence( $table ) );

						if ( isset( $id ) ) $this[ $primary ] = $id;

					}

					$this->setClean();

				}

			}

			return $this;

		}

		// make list of all rows in this tree

		$list = array();
		$this->listRows( $list );
		$count = count( $list );

		// keep iterating and saving until all references are known

		while ( true ) {

			$solvable = false;
			$clean = 0;

			foreach ( $list as $row ) {

				$row->updateReferences();

				$missing = $row->getMissing();

				if ( empty( $missing ) ) {

					$row->save( false );
					$row->updateBackReferences();
					$solvable = true;

				}

				if ( $row->isClean() ) ++$clean;

			}

			if ( !$solvable ) {

				throw new \LogicException(
					'Cannot recursively save structure (' . $table . ') - add required values or allow NULL'
				);

			}

			if ( $clean === $count ) break;

		}

		return $this;

	}

	/**
	 * @param array $list
	 */
	protected function listRows( &$list ) {

		$list[] = $this;

		foreach ( $this->_properties as $column => $value ) {

			if ( $value instanceof Row ) {

				$value->listRows( $list );

			} else if ( is_array( $value ) ) {

				foreach ( $value as $row ) {

					$row->listRows( $list );

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
	function updateReferences() {

		$unknown = array();
		$db = $this->getDatabase();

		foreach ( $this->_properties as $column => $value ) {

			if ( $value instanceof Row ) {

				$key = $db->getReference( $this->getTable(), $column );
				$this[ $key ] = $value->getId();

			}

		}

		return $unknown;

	}

	/**
	 * Check back references and set respective keys
	 *
	 * @return $this
	 */
	function updateBackReferences() {

		$id = $this->getId();

		if ( is_array( $id ) ) return $this;

		$db = $this->getDatabase();

		foreach ( $this->_properties as $column => $value ) {

			if ( is_array( $value ) ) {

				$key = $db->getBackReference( $this->getTable(), $column );

				foreach ( $value as $row ) {

					$row->{ $key } = $id;

				}

			}

		}

		return $this;

	}

	/**
	 * Get missing columns, i.e. any that is null but required by the schema
	 *
	 * @return array
	 */
	function getMissing() {

		$missing = array();
		$required = $this->getDatabase()->getRequired( $this->getTable() );

		foreach ( $required as $column => $true ) {

			if ( !isset( $this[ $column ] ) ) {

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
	function update( $data, $recursive = true ) {

		return $this->setData( $data )->save( $recursive );

	}

	/**
	 * Delete this row
	 *
	 * @return $this|Row
	 */
	function delete() {

		$db = $this->getDatabase();
		$table = $this->getTable();

		$result = $db->table( $table );

		$idCondition = $this->getOriginalId();

		if ( $idCondition === null ) return $this;

		if ( !is_array( $idCondition ) ) {

			$primary = $db->getPrimary( $table );
			$idCondition = array( $primary => $idCondition );

		}

		$result->where( $idCondition )->delete();

		$this->_originalId = null;

		return $this->setDirty();

	}

	/**
	 * Does this row exist?
	 *
	 * @return bool
	 */
	function exists() {

		return $this->_originalId !== null;

	}

	/**
	 * Is this row clean, i.e. in sync with the database?
	 *
	 * @return bool
	 */
	function isClean() {

		return empty( $this->_modified );

	}

	/**
	 * Set this row to "clean" state, i.e. in sync with database
	 *
	 * @return $this
	 */
	function setClean() {

		$id = $this->getId();

		if ( $id === null ) {

			throw new \LogicException( 'Cannot set Row "clean" without id' );

		}

		$this->_originalId = $id;
		$this->_modified = array();

		return $this;

	}

	/**
	 * Set this row to "dirty" state, i.e. out of sync with database
	 *
	 * @return $this
	 */
	function setDirty() {

		$this->_modified = $this->_properties; // copy...

		return $this;

	}

	/**
	 * Get root result or row
	 *
	 * @return Result|Row
	 */
	function getRoot() {

		$result = $this->getResult();

		if ( $result ) return $result->getRoot();

		return $this;

	}

	/**
	 * Get value from cache
	 *
	 * @param $key
	 * @return mixed
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
	 * Get column, used by result if row is parent
	 *
	 * @param string $key
	 * @return array
	 */
	function getLocalKeys( $key ) {

		if ( isset( $this[ $key ] ) ) {

			return array( $this[ $key ] );

		}

		return array();

	}

	/**
	 * Get global keys of parent result, or column if row is root
	 *
	 * @param string $key
	 * @return array
	 */
	function getGlobalKeys( $key ) {

		$result = $this->getResult();

		if ( $result ) return $result->getGlobalKeys( $key );

		return $this->getLocalKeys( $key );

	}

	/**
	 * Get the database
	 *
	 * @return Database
	 */
	function getDatabase() {

		return $this->_db;

	}

	/**
	 * Get the bound result, if any
	 *
	 * @return Result|null
	 */
	function getResult() {

		return $this->_result;

	}

	/**
	 * Get the table
	 *
	 * @return string
	 */
	function getTable() {

		return $this->_table;

	}

	/**
	 * Returns true if the given property exists, even if its value is null
	 *
	 * @param string $name Property name to check
	 * @return bool
	 */
	function hasProperty( $name ) {

		return array_key_exists( $name, $this->_properties );

	}

	// ArrayAccess

	/**
	 * @param string $offset
	 * @return bool
	 */
	function offsetExists( $offset ) {

		return $this->__isset( $offset );

	}

	/**
	 * @param string $offset
	 * @return mixed
	 */
	function &offsetGet( $offset ) {

		return $this->__get( $offset );

	}

	/**
	 * @param string $offset
	 * @param mixed $value
	 */
	function offsetSet( $offset, $value ) {

		$this->__set( $offset, $value );

	}

	/**
	 * @param string $offset
	 */
	function offsetUnset( $offset ) {

		$this->__unset( $offset );

	}

	// IteratorAggregate

	/**
	 * @return \ArrayIterator
	 */
	function getIterator() {

		return new \ArrayIterator( $this->_properties );

	}

	// JsonSerializable

	/**
	 * @return array
	 */
	function jsonSerialize() {

		$array = array();

		foreach ( $this->_properties as $key => $value ) {

			if ( $value instanceof \JsonSerializable ) {

				$array[ $key ] = $value->jsonSerialize();

			} else if ( $value instanceof \DateTime ) {

				$array[ $key ] = $value->format( 'Y-m-d H:i:s' );

			} else if ( is_array( $value ) ) { // list of Rows

				foreach ( $value as $i => $row ) {

					$value[ $i ] = $row->jsonSerialize();

				}

				$array[ $key ] = $value;

			} else {

				$array[ $key ] = $value;

			}

		}

		return $array;

	}

	//

	/** @var Database */
	protected $_db;

	/** @var string */
	protected $_table;

	/** @var Result|null */
	protected $_result;

	/** @var array */
	protected $_properties = array();

	/** @var array */
	protected $_modified = array();

	/** @var null|string|array */
	protected $_originalId;

	//

	/** @var array */
	protected $_cache = array();

}
