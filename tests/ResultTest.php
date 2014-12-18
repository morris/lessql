<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class ResultTest extends BaseTest {

	function testPrimary() {

		$db = self::$db;

		$a = $db->user( 2 );
		$b = $db->user( 3 );

		$this->assertEquals( 'Editor', $a->name );
		$this->assertEquals( 'Chief Editor', $b[ 'name' ] );

	}

	function testVia() {

		$db = self::$db;

		$post = $db->post( 12 );

		$author = $post->user()->via( 'author_id' )->fetch();
		$editor = $post->user()->via( 'editor_id' )->fetch();
		$posts = $author->postList()->via( 'author_id' );

		$this->assertEquals( 1, $author->id );
		$this->assertEquals( 2, $editor->id );
		$this->assertEquals( array( '11', '12' ), $posts->getLocalKeys( 'id' ) );

		$this->assertEquals( array(
			"SELECT * FROM `post` WHERE `id` = 12",
			"SELECT * FROM `user` WHERE `id` = '1'",
			"SELECT * FROM `user` WHERE `id` = '2'",
			"SELECT * FROM `post` WHERE `author_id` = '1'"
		), $this->queries );

	}

	function testInsert() {

		$db = self::$db;

		$db->begin();
		$db->dummy()->insert( array() ); // does nothing
		$db->dummy()->insert( array( 'test' => 42 ) );
		$db->dummy()->insert( array(
			array( 'test' => 1 ),
			array( 'test' => 2 ),
			array( 'test' => 3 )
		) );
		$db->commit();

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `test` ) VALUES ( 42 )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( 1 )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( 2 )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( 3 )"
		), $this->queries );

	}

	function testInsertPrepared() {

		$db = self::$db;

		$db->begin();
		$db->dummy()->insert( array(
			array( 'test' => 1 ),
			array( 'test' => 2 ),
			array( 'test' => 3 )
		), 'prepared' );
		$db->commit();

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )"
		), $this->queries );

		$this->assertEquals( array(
			array( 1 ),
			array( 2 ),
			array( 3 ),
		), $this->params );

	}

	function testInsertBatch() {

		$db = self::$db;

		// not supported by sqlite < 3.7, need try/catch

		try {

			$db->begin();
			$db->dummy()->insert( array(
				array( 'test' => 1 ),
				array( 'test' => 2 ),
				array( 'test' => 3 )
			), 'batch' );
			$db->commit();

		} catch ( \Exception $ex ) {

			$db->rollback();

		}

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `test` ) VALUES ( 1 ), ( 2 ), ( 3 )",
		), $this->queries );

	}

	function testKeys() {

		$db = self::$db;

		$a = array();

		foreach ( $db->post() as $post ) {

			$this->assertEquals( array( $post[ 'id' ] ), $post->getLocalKeys( 'id' ) );
			$this->assertEquals( array( 11, 12, 13 ), $post->getGlobalKeys( 'id' ) );
			$this->assertEquals( array( $post[ 'author_id' ] ), $post->getLocalKeys( 'author_id' ) );
			$this->assertEquals( array( '1', '2' ), $post->getGlobalKeys( 'author_id' ) );

			$userResult = $post->author();

			$this->assertEquals( array( $post[ 'author_id' ] ), $userResult->getLocalKeys( 'id' ) );
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

			$author = $post->author()->fetch();
			$editor = $post->editor()->fetch();

			$t = array();

			$t[ 'title' ] = $post->title;
			$t[ 'author' ] = $author->name;
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

}
