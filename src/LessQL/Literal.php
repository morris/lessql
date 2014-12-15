<?php

namespace LessQL;

/**
 * SQL Literal
 */
class Literal {

	/**
	 * Constructor
	 */
	function __construct( $value ) {

		$this->value = $value;

	}

	/**
	 * Return the literal value
	 */
	function __toString() {

		return $this->value;

	}

	public $value;

}
