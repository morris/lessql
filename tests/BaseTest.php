<?php

require_once 'vendor/autoload.php';

class BaseTest extends PHPUnit_Framework_TestCase {

	static $pdo;
	static $db;

	static function setUpBeforeClass() {

		// do this only once
		if ( isset( self::$db ) ) return;

		// pdo

		// sqlite
		self::$pdo = new \PDO( 'sqlite:tests/shop.sqlite3' );

		// mysql
		//self::$pdo = new \PDO( 'mysql:host=localhost;dbname=test', 'root', 'pw' );

		// postgres
		//self::$pdo = new \PDO( 'pgsql:host=localhost;port=5432;dbname=test;user=postgres;password=pw' );

		//

		self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

		$driver = self::$pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );

		// db

		self::$db = new \LessQL\Database( self::$pdo );

		// hints

		self::$db->setAlias( 'author', 'user' );
		self::$db->setAlias( 'editor', 'user' );
		self::$db->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );

		self::$db->setAlias( 'edit_post', 'post' );
		self::$db->setBackReference( 'user', 'edit_post', 'editor_id' );

		// schema

		self::$pdo->beginTransaction();

		$q = array( self::$pdo, 'query' );
		$e = array( self::$db, 'quoteIdentifier' );

		//

		if ( $driver === 'sqlite' ) {

			$p = "INTEGER PRIMARY KEY AUTOINCREMENT";

		}

		if ( $driver === 'mysql' ) {

			$p = "INTEGER PRIMARY KEY AUTO_INCREMENT";

		}

		if ( $driver === 'pgsql' ) {

			self::$db->setIdentifierDelimiter( '"' );
			$p = "SERIAL PRIMARY KEY";

		}

		$q( "DROP TABLE IF EXISTS " . $e( "user" ) );

		$q( "CREATE TABLE " . $e( "user" ) . " (
			id $p,
			type varchar(30) DEFAULT 'user',
			name varchar(30) NOT NULL,
			address_id INTEGER DEFAULT NULL,
			billing_address_id INTEGER DEFAULT NULL,
			post_id INTEGER DEFAULT NULL
		)" );

		$q( "DROP TABLE IF EXISTS post" );

		$q( "CREATE TABLE post (
			id $p,
			author_id INTEGER DEFAULT NULL,
			editor_id INTEGER DEFAULT NULL,
			published VARCHAR(30) DEFAULT NULL,
			title VARCHAR(30) NOT NULL
		)" );

		$q( "DROP TABLE IF EXISTS category" );

		$q( "CREATE TABLE category (
			id $p,
			title varchar(30) NOT NULL
		)" );

		$q( "DROP TABLE IF EXISTS categorization" );

		$q( "CREATE TABLE categorization (
			category_id INTEGER NOT NULL,
			post_id INTEGER NOT NULL
		)" );

		$q( "DROP TABLE IF EXISTS dummy" );

		$q( "CREATE TABLE dummy (
			id $p,
			test INTEGER
		)" );

		// data

		// users

		$q( "DELETE FROM " . $e( "user" ) . "" );

		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (1, 'Writer')" );
		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (2, 'Editor')" );
		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (3, 'Chief Editor')" );

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

		// postgres sequences
		if ( $driver === 'pgsql' ) {

			$q( "SELECT setval('user_id_seq', 3)" );
			$q( "SELECT setval('post_id_seq', 13)" );
			$q( "SELECT setval('category_id_seq', 23)" );
			$q( "SELECT setval('dummy_id_seq', 1, false)" );

		}

		self::$pdo->commit();

	}

	function setUp() {

		self::$db->setQueryCallback( array( $this, 'log' ) );
		$this->queries = array();
		$this->params = array();

	}

	function log( $query, $params ) {

		$this->queries[] = str_replace( '"', '`', $query );
		$this->params[] = $params;

	}

	function testDummy() {

	}

}
