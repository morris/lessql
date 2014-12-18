<?php

require_once 'vendor/autoload.php';

class BaseTest extends PHPUnit_Framework_TestCase {

	static $pdo;
	static $db;

	static function setUpBeforeClass() {

		// do this only once
		if ( isset( self::$db ) ) return;

		// pdo

		self::$pdo = new \PDO( 'sqlite:tests/shop.sqlite3' );
		self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

		$q = array( self::$pdo, 'query' );

		self::$pdo->beginTransaction();

		// schema

		$q( "DROP TABLE IF EXISTS user" );

		$q( "CREATE TABLE user (
			id INTEGER,
			type varchar(30) DEFAULT 'user',
			name varchar(30) NOT NULL,
			address_id INTEGER DEFAULT NULL,
			billing_address_id INTEGER DEFAULT NULL,
			post_id INTEGER DEFAULT NULL,
			PRIMARY KEY (id)
		)" );

		$q( "DROP TABLE IF EXISTS post" );

		$q( "CREATE TABLE post (
			id INTEGER,
			author_id INTEGER DEFAULT NULL,
			editor_id INTEGER DEFAULT NULL,
			published datetime DEFAULT NULL,
			title VARCHAR(30) NOT NULL,
			PRIMARY KEY (id)
		)" );

		$q( "DROP TABLE IF EXISTS category" );

		$q( "CREATE TABLE category (
			id INTEGER,
			title varchar(30) NOT NULL,
			PRIMARY KEY (id)
		)" );

		$q( "DROP TABLE IF EXISTS categorization" );

		$q( "CREATE TABLE categorization (
			category_id INTEGER NOT NULL,
			post_id INTEGER NOT NULL
		)" );

		$q( "DROP TABLE IF EXISTS dummy" );

		$q( "CREATE TABLE dummy (
			id INTEGER NOT NULL,
			test INTEGER NOT NULL,
			PRIMARY KEY (id)
		)" );

		// data

		// users

		$q( "DELETE FROM user" );

		$q( "INSERT INTO user (id, name) VALUES (1, 'Writer')" );
		$q( "INSERT INTO user (id, name) VALUES (2, 'Editor')" );
		$q( "INSERT INTO user (id, name) VALUES (3, 'Chief Editor')" );

		// posts

		$q( "DELETE FROM post" );

		$q( "INSERT INTO post (id, title, published, author_id, editor_id) VALUES (11, 'Championship won', '2014-09-18', 1, NULL)" );
		$q( "INSERT INTO post (id, title, published, author_id, editor_id) VALUES (12, 'Foo released', '2014-09-15', 1, 2)" );
		$q( "INSERT INTO post (id, title, published, author_id, editor_id) VALUES (13, 'Bar released', '2014-09-21', 2, 3)" );

		// categories

		$q( "DELETE FROM category" );

		$q( "INSERT INTO category (id, title) VALUES (21, 'Tech')" );
		$q( "INSERT INTO category (id, title) VALUES (22, 'Sports')" );
		$q( "INSERT INTO category (id, title) VALUES (23, 'Basketball')" );

		// categorization

		$q( "DELETE FROM categorization" );

		$q( "INSERT INTO categorization (category_id, post_id) VALUES (22, 11)" );
		$q( "INSERT INTO categorization (category_id, post_id) VALUES (23, 11)" );
		$q( "INSERT INTO categorization (category_id, post_id) VALUES (21, 12)" );
		$q( "INSERT INTO categorization (category_id, post_id) VALUES (21, 13)" );

		// dummy

		$q( "DELETE FROM dummy" );

		self::$pdo->commit();

		// db

		self::$db = new \LessQL\Database( self::$pdo );

		// hints

		self::$db->setAlias( 'author', 'user' );
		self::$db->setAlias( 'editor', 'user' );
		self::$db->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );

		self::$db->setAlias( 'edit_post', 'post' );
		self::$db->setBackReference( 'user', 'edit_post', 'editor_id' );

	}

	function setUp() {

		self::$db->setQueryCallback( array( $this, 'log' ) );
		$this->queries = array();
		$this->params = array();

	}

	function log( $query, $params ) {

		$this->queries[] = $query;
		$this->params[] = $params;

	}

	function testDummy() {

	}

}
