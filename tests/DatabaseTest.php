<?php

require_once 'vendor/autoload.php';

class DatabaseTest extends PHPUnit_Framework_TestCase {

	static $pdo;
	static $db;

	static function setUpBeforeClass() {

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
			user_id INTEGER DEFAULT NULL,
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

		// data

		// users

		$q( "DELETE FROM user" );

		$q( "INSERT INTO user (id, name) VALUES (1, 'Writer')" );
		$q( "INSERT INTO user (id, name) VALUES (2, 'Editor')" );
		$q( "INSERT INTO user (id, name) VALUES (3, 'Chief Editor')" );

		// posts

		$q( "DELETE FROM post" );

		$q( "INSERT INTO post (id, title, published, user_id, editor_id) VALUES (11, 'Championship won', '2014-09-18', 1, NULL)" );
		$q( "INSERT INTO post (id, title, published, user_id, editor_id) VALUES (12, 'Foo released', '2014-09-15', 1, 2)" );
		$q( "INSERT INTO post (id, title, published, user_id, editor_id) VALUES (13, 'Bar released', '2014-09-21', 2, 3)" );

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

		self::$pdo->commit();

		// db

		self::$db = new \LessQL\Database( self::$pdo );

		// hints

		self::$db->setAlias( 'editor', 'user' );
		self::$db->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );

		self::$db->setAlias( 'edit_post', 'post' );
		self::$db->setBackReference( 'user', 'edit_post', 'editor_id' );

	}

	function setUp() {

		self::$db->onQuery = array( $this, 'log' );
		$this->queries = array();

	}

	function log( $query ) {

		$this->queries[] = $query;

	}

	function testPrimary() {

		$db = self::$db;

		$a = $db->user( 2 );
		$b = $db->user( 3 );

		$this->assertEquals( 'Editor', $a->name );
		$this->assertEquals( 'Chief Editor', $b[ 'name' ] );

	}

	function testKeys() {

		$db = self::$db;

		$a = array();

		foreach ( $db->post() as $post ) {

			$this->assertEquals( array( $post[ 'id' ] ), $post->getLocalKeys( 'id' ) );
			$this->assertEquals( array( 11, 12, 13 ), $post->getGlobalKeys( 'id' ) );
			$this->assertEquals( array( $post[ 'user_id' ] ), $post->getLocalKeys( 'user_id' ) );
			$this->assertEquals( array( '1', '2' ), $post->getGlobalKeys( 'user_id' ) );

			$userResult = $post->user();

			$this->assertEquals( array( $post[ 'user_id' ] ), $userResult->getLocalKeys( 'id' ) );
			$this->assertEquals( array( '1', '2' ), $userResult->getGlobalKeys( 'id' ) );

			foreach ( $post->categorizationList() as $categorization ) {

				$this->assertEquals( array( $post[ 'id' ] ), $categorization->getLocalKeys( 'post_id' ) );
				$this->assertEquals( array( '11', '12', '13' ), $categorization->getGlobalKeys( 'post_id' ) );

			}

			$categorizationResult = $post->categorizationList();
			$categoryResult = $categorizationResult->category();

			$this->assertEquals( array( '22', '23', '21' ), $categorizationResult->getGlobalKeys( 'category_id' ) );

			if ( $post[ 'id'] == 11 ) {

				$this->assertEquals( array( '22', '23' ), $categorizationResult->getLocalKeys( 'category_id' ) );
				$this->assertEquals( 2, $categoryResult->rowCount() );
				$this->assertEquals( array( '22', '23' ), $categoryResult->getLocalKeys( 'id' ) );

			} else {

				$this->assertEquals( array( '21' ), $categorizationResult->getLocalKeys( 'category_id' ) );

			}

		}

	}

	function testTraversal() {

		$db = self::$db;

		$posts = array();

		foreach ( $db->post()->orderBy( 'published', 'DESC' ) as $post ) {

			$user = $post->user()->fetch();
			$editor = $post->editor()->fetch();

			$t = array();

			$t[ 'title' ] = $post->title;
			$t[ 'author' ] = $user->name;
			$t[ 'editor' ] = $editor ? $editor->name : null;
			$t[ 'categories' ] = array();

			foreach ( $post->categorizationList()->category() as $category ) {

				$t[ 'categories' ][] = $category->title;

			}

			$posts[] = $t;

		}

		$this->assertEquals( array(
			"SELECT * FROM `post` ORDER BY `published` DESC",
			"SELECT * FROM `user` WHERE `id` IN ( '2', '1' )",
			"SELECT * FROM `user` WHERE `id` IN ( '3', '2' )",
			"SELECT * FROM `categorization` WHERE `post_id` IN ( '13', '11', '12' )",
			"SELECT * FROM `category` WHERE `id` IN ( '22', '23', '21' )"
		), $this->queries );

		$this->assertEquals( array(
			array(
				'title' => 'Bar released',
				'categories' => array( 'Tech' ),
				'author' => 'Editor',
				'editor' => 'Chief Editor'
			),
			array(
				'title' => 'Championship won',
				'categories' => array( 'Sports', 'Basketball' ),
				'author' => 'Writer',
				'editor' => null
			),
			array(
				'title' => 'Foo released',
				'categories' => array( 'Tech' ),
				'author' => 'Writer',
				'editor' => 'Editor'
			)
		), $posts );

	}

	function testBackReference() {

		$db = self::$db;

		foreach ( $db->user() as $user ) {

			$posts_as_editor = $user->edit_postList()->fetchAll();

		}

		$this->assertEquals( array(
			"SELECT * FROM `user`",
			"SELECT * FROM `post` WHERE `editor_id` IN ( '1', '2', '3' )"
		), $this->queries );

	}

	function testSave() {

		$db = self::$db;

		$row = $db->createRow( 'post', array(
			'title' => 'Smaugs Desolation Review',
			'user' => array(
				'name' => 'Fantasy Guy'
			),
			'editor' => array(
				'name' => 'Big Boss',
				'post' => array(
					'title' => 'Favorite Post'
				)
			),
			'categorizationList' => array(

				array(
					'category' => array( 'title' => 'Movies' )
				),
				array(
					'category' => array( 'title' => 'Fantasy' )
				)

			)
		) );

		$db->begin();
		$row->save();
		$db->commit();

		$this->assertEquals( array(
			"INSERT INTO `post` ( `title`, `user_id`, `editor_id` ) VALUES ( 'Smaugs Desolation Review', NULL, NULL )",
			"INSERT INTO `user` ( `name` ) VALUES ( 'Fantasy Guy' )",
			"INSERT INTO `user` ( `name`, `post_id` ) VALUES ( 'Big Boss', NULL )",
			"INSERT INTO `post` ( `title` ) VALUES ( 'Favorite Post' )",
			"INSERT INTO `category` ( `title` ) VALUES ( 'Movies' )",
			"INSERT INTO `category` ( `title` ) VALUES ( 'Fantasy' )",
			"UPDATE `post` SET `user_id` = '4', `editor_id` = '5' WHERE `id` = '14'",
			"UPDATE `user` SET `post_id` = '15' WHERE `id` = '5'",
			"INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '24' )",
			"INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '25' )"
		), $this->queries );

	}

	function testJson() {

		$db = self::$db;

		$posts = json_encode( $db->post()->orderBy( 'published' ) );
		$post = json_encode( $db->post()->where( 'published > ?', '2015-01-01' )->fetch() );

		$nested = json_encode( $db->createRow( 'post', array(
			'title' => 'Smaugs Desolation Review',
			'user' => array(
				'name' => 'Fantasy Guy'
			),
			'editor' => array(
				'name' => 'Big Boss',
				'post' => array(
					'title' => 'Favorite Post'
				)
			),
			'categorizationList' => array(

				array(
					'category' => array( 'title' => 'Movies' )
				),
				array(
					'category' => array( 'title' => 'Fantasy' )
				)

			)
		) ) );

	}

	// TODO add more tests

}
