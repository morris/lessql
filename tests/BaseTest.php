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

		self::query( "DROP TABLE IF EXISTS " . self::quoteIdentifier( "user" ) );

		self::query( "CREATE TABLE " . self::quoteIdentifier( "user" ) . " (
			id $p,
			name varchar(30) NOT NULL
		)" );

		self::query( "DROP TABLE IF EXISTS post" );

		self::query( "CREATE TABLE post (
			id $p,
			author_id INTEGER DEFAULT NULL,
			editor_id INTEGER DEFAULT NULL,
			is_published INTEGER DEFAULT 0,
			date_published VARCHAR(30) DEFAULT NULL,
			title VARCHAR(30) NOT NULL
		)" );

		self::query( "DROP TABLE IF EXISTS category" );

		self::query( "CREATE TABLE category (
			id $p,
			title varchar(30) NOT NULL
		)" );

		self::query( "DROP TABLE IF EXISTS categorization" );

		self::query( "CREATE TABLE categorization (
			category_id INTEGER NOT NULL,
			post_id INTEGER NOT NULL
		)" );

		self::query( "DROP TABLE IF EXISTS dummy" );

		self::query( "CREATE TABLE dummy (
			id $p,
			test INTEGER
		)" );

		self::$pdo->commit();

	}

	static function reset() {

		self::$pdo->beginTransaction();

		// sequences

		if ( self::driver() === 'sqlite' ) {

			self::query( "DELETE FROM sqlite_sequence WHERE name='user'" );
			self::query( "DELETE FROM sqlite_sequence WHERE name='post'" );
			self::query( "DELETE FROM sqlite_sequence WHERE name='category'" );
			self::query( "DELETE FROM sqlite_sequence WHERE name='dummy'" );

		}

		if ( self::driver() === 'mysql' ) {

			self::query( "ALTER TABLE user AUTO_INCREMENT = 1" );
			self::query( "ALTER TABLE post AUTO_INCREMENT = 1" );
			self::query( "ALTER TABLE category AUTO_INCREMENT = 1" );
			self::query( "ALTER TABLE dummy AUTO_INCREMENT = 1" );

		}

		if ( self::driver() === 'pgsql' ) {

			self::query( "SELECT setval('user_id_seq', 3)" );
			self::query( "SELECT setval('post_id_seq', 13)" );
			self::query( "SELECT setval('category_id_seq', 23)" );
			self::query( "SELECT setval('dummy_id_seq', 1, false)" );

		}

		// data

		// users

		self::query( "DELETE FROM " . self::quoteIdentifier( "user" ) . "" );

		self::query( "INSERT INTO " . self::quoteIdentifier( "user" ) . " (id, name) VALUES (1, 'Writer')" );
		self::query( "INSERT INTO " . self::quoteIdentifier( "user" ) . " (id, name) VALUES (2, 'Editor')" );
		self::query( "INSERT INTO " . self::quoteIdentifier( "user" ) . " (id, name) VALUES (3, 'Chief Editor')" );

		// posts

		self::query( "DELETE FROM post" );

		self::query( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (11, 'Championship won', '2014-09-18', 1, NULL)" );
		self::query( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (12, 'Foo released', '2014-09-15', 1, 2)" );
		self::query( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (13, 'Bar released', '2014-09-21', 2, 3)" );

		// categories

		self::query( "DELETE FROM category" );

		self::query( "INSERT INTO category (id, title) VALUES (21, 'Tech')" );
		self::query( "INSERT INTO category (id, title) VALUES (22, 'Sports')" );
		self::query( "INSERT INTO category (id, title) VALUES (23, 'Basketball')" );

		// categorization

		self::query( "DELETE FROM categorization" );

		self::query( "INSERT INTO categorization (category_id, post_id) VALUES (22, 11)" );
		self::query( "INSERT INTO categorization (category_id, post_id) VALUES (23, 11)" );
		self::query( "INSERT INTO categorization (category_id, post_id) VALUES (21, 12)" );
		self::query( "INSERT INTO categorization (category_id, post_id) VALUES (21, 13)" );

		// dummy

		self::query( "DELETE FROM dummy" );

		self::$pdo->commit();

	}

	static function clearTransaction() {

		try {

			self::$pdo->rollBack();

		} catch ( \Exception $ex ) {

			// ignore

		}

	}

	static function query( $q ) {

		return self::$pdo->query( $q );

	}

	static function quoteIdentifier( $id ) {

		return self::$db->quoteIdentifier( $id );

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
