<?php

namespace LessQL;

/**
 * SQL Literal
 */
class Literal {

	/**
	 * Constructor
	 *
	 * @param string
	 */
	function __construct( $value ) {

		$this->value = $value;

	}

	/**
	 * Return the literal value
	 *
	 * @return string
	 */
	function __toString() {

		return $this->value;

	}

	/** @var string */
	public $value;

}
