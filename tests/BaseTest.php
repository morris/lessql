<?php

require_once 'vendor/autoload.php';

class BaseTest extends PHPUnit_Framework_TestCase {

	// static

	static $pdo;
	static $db;

	static function setUpBeforeClass() {

		// do this only once
		if ( isset( self::$db ) ) return;

		// pdo
		self::pdo();
		self::lessql();
		self::schema();
		self::reset();

	}

	static function pdo() {

		if ( self::$pdo ) return self::$pdo;

		// sqlite
		self::$pdo = new \PDO( 'sqlite:tests/shop.sqlite3' );

		// mysql
		//self::$pdo = new \PDO( 'mysql:host=localhost;dbname=test', 'root', 'pass' );

		// postgres
		//self::$pdo = new \PDO( 'pgsql:host=localhost;port=5432;dbname=test;user=postgres;password=pass' );

		//

		self::$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

		return self::$pdo;

	}

	static function driver() {

		return self::$pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );

	}

	static function lessql() {

		if ( self::$db ) return self::$db;

		self::$db = new \LessQL\Database( self::pdo() );

		self::$db->setAlias( 'author', 'user' );
		self::$db->setAlias( 'editor', 'user' );
		self::$db->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );

		self::$db->setAlias( 'edit_post', 'post' );
		self::$db->setBackReference( 'user', 'edit_post', 'editor_id' );

	}

	static function schema() {

		$q = array( self::$pdo, 'query' );
		$e = array( self::$db, 'quoteIdentifier' );

		self::$pdo->beginTransaction();

		//

		if ( self::driver() === 'sqlite' ) {

			$p = "INTEGER PRIMARY KEY AUTOINCREMENT";

		}

		if ( self::driver() === 'mysql' ) {

			$p = "INTEGER PRIMARY KEY AUTO_INCREMENT";

		}

		if ( self::driver() === 'pgsql' ) {

			self::$db->setIdentifierDelimiter( '"' );
			$p = "SERIAL PRIMARY KEY";

		}

		$q( "DROP TABLE IF EXISTS " . $e( "user" ) );

		$q( "CREATE TABLE " . $e( "user" ) . " (
			id $p,
			name varchar(30) NOT NULL
		)" );

		$q( "DROP TABLE IF EXISTS post" );

		$q( "CREATE TABLE post (
			id $p,
			author_id INTEGER DEFAULT NULL,
			editor_id INTEGER DEFAULT NULL,
			is_published INTEGER DEFAULT 0,
			date_published VARCHAR(30) DEFAULT NULL,
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

		self::$pdo->commit();

	}

	static function reset() {

		$q = array( self::$pdo, 'query' );
		$e = array( self::$db, 'quoteIdentifier' );

		self::$pdo->beginTransaction();

		// sequences

		if ( self::driver() === 'sqlite' ) {

			$q( "DELETE FROM sqlite_sequence WHERE name='user'" );
			$q( "DELETE FROM sqlite_sequence WHERE name='post'" );
			$q( "DELETE FROM sqlite_sequence WHERE name='category'" );
			$q( "DELETE FROM sqlite_sequence WHERE name='dummy'" );

		}

		if ( self::driver() === 'mysql' ) {

			$q( "ALTER TABLE user AUTO_INCREMENT = 1" );
			$q( "ALTER TABLE post AUTO_INCREMENT = 1" );
			$q( "ALTER TABLE category AUTO_INCREMENT = 1" );
			$q( "ALTER TABLE dummy AUTO_INCREMENT = 1" );

		}

		if ( self::driver() === 'pgsql' ) {

			$q( "SELECT setval('user_id_seq', 3)" );
			$q( "SELECT setval('post_id_seq', 13)" );
			$q( "SELECT setval('category_id_seq', 23)" );
			$q( "SELECT setval('dummy_id_seq', 1, false)" );

		}

		// data

		// users

		$q( "DELETE FROM " . $e( "user" ) . "" );

		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (1, 'Writer')" );
		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (2, 'Editor')" );
		$q( "INSERT INTO " . $e( "user" ) . " (id, name) VALUES (3, 'Chief Editor')" );

		// posts

		$q( "DELETE FROM post" );

		$q( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (11, 'Championship won', '2014-09-18', 1, NULL)" );
		$q( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (12, 'Foo released', '2014-09-15', 1, 2)" );
		$q( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (13, 'Bar released', '2014-09-21', 2, 3)" );

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

	}

	static function clearTransaction() {

		try {

			self::$pdo->rollBack();

		} catch ( \Exception $ex ) {

			// ignore

		}

	}

	// instance

	protected $needReset = false;

	function setUp() {

		self::$db->setQueryCallback( array( $this, 'onQuery' ) );
		$this->queries = array();
		$this->params = array();

	}

	function onQuery( $query, $params ) {

		if ( substr( $query, 0, 6 ) !== 'SELECT' ) {

			$this->needReset = true;

		}

		$this->queries[] = str_replace( '"', '`', $query );
		$this->params[] = $params;

	}

	function tearDown() {

		self::clearTransaction();

		if ( $this->needReset ) {

			self::reset();

		}

	}

	function testDummy() {

	}

}
