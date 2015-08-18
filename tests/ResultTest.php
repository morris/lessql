<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class ResultTest extends BaseTest {

	function testPrimary() {

		$db = self::$db;

		$a = $db->user( 2 );
		$b = $db->user( 3 );
		$c = $db->user( 42 );

		$this->assertNotNull( $a );
		$this->assertNotNull( $b );
		$this->assertNull( $c );
		$this->assertTrue( $a->exists() );
		$this->assertTrue( $b->exists() );
		$this->assertEquals( 'Editor', $a->name );
		$this->assertEquals( 'Chief Editor', $b[ 'name' ] );

	}

	function testVia() {

		$db = self::$db;

		$post = $db->post( 12 );

		$this->assertNotNull( $post );

		$author = $post->user()->via( 'author_id' )->fetch();
		$editor = $post->user()->via( 'editor_id' )->fetch();
		$posts = $author->postList()->via( 'author_id' );

		$this->assertEquals( 1, $author->id );
		$this->assertEquals( 2, $editor->id );
		$this->assertEquals( array( '11', '12' ), $posts->getLocalKeys( 'id' ) );

		$this->assertEquals( array(
			"SELECT * FROM `post` WHERE `id` = '12'",
			"SELECT * FROM `user` WHERE `id` = '1'",
			"SELECT * FROM `user` WHERE `id` = '2'",
			"SELECT * FROM `post` WHERE `author_id` = '1'"
		), $this->queries );

	}

	function testInsert() {

		$db = self::$db;

		$db->begin();
		$db->dummy()->insert( array() ); // does nothing
		$db->dummy()->insert( array( 'id' => 1, 'test' => 42 ) );
		$db->dummy()->insert( array(
			array( 'id' => 2,  'test' => 1 ),
			array( 'id' => 3,  'test' => 2 ),
			array( 'id' => 4,  'test' => 3 )
		) );
		$db->commit();

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '1', '42' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '2', '1' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '3', '2' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '4', '3' )"
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
			"INSERT INTO `dummy` ( `test` ) VALUES ( '1' ), ( '2' ), ( '3' )",
		), $this->queries );

	}

	function testUpdate() {

		$db = self::$db;

		$db->begin();

		$db->dummy()->update( array() );
		$db->dummy()->update( array( 'test' => 42 ) );
		$db->dummy()->where( 'test', 1 )->update( array( 'test' => 42 ) );


		$queries = $this->queries;
		$db->dummy()->insert( array( 'id' => 1, 'test' => 44 ) );
		$db->dummy()->insert( array( 'id' => 2, 'test' => 42 ) );
		$db->dummy()->insert( array( 'id' => 3, 'test' => 45 ) );
		$db->dummy()->insert( array( 'id' => 4, 'test' => 47 ) );
		$db->dummy()->insert( array( 'id' => 5, 'test' => 48 ) );
		$db->dummy()->insert( array( 'id' => 6, 'test' => 43 ) );
		$db->dummy()->insert( array( 'id' => 7, 'test' => 41 ) );
		$db->dummy()->insert( array( 'id' => 8, 'test' => 46 ) );
		$this->queries = $queries;

		$db->commit();

		$db->begin();
		$db->dummy()->where( 'test > 42' )->limit( 2, 2 )->update( array( 'test' => 42 ) );
		$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->limit( 2 )->update( array( 'test' => 42 ) );
		$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->update( array( 'test' => 42 ) );
		$db->commit();

		$this->assertEquals( array(
			"UPDATE `dummy` SET `test` = '42'",
			"UPDATE `dummy` SET `test` = '42' WHERE `test` = '1'",
			"SELECT * FROM `dummy` WHERE test > 42 LIMIT 2 OFFSET 2",
			"UPDATE `dummy` SET `test` = '42' WHERE `id` IN ( '4', '5' )",
			"SELECT * FROM `dummy` WHERE test > 42 ORDER BY `test` ASC LIMIT 2",
			"UPDATE `dummy` SET `test` = '42' WHERE `id` IN ( '6', '1' )",
			"UPDATE `dummy` SET `test` = '42' WHERE test > 42"
		), $this->queries );

	}

	function testUpdatePrimary() {

		$db = self::$db;

		$db->begin();

		$db->category()->where( 'id > 21' )->limit( 2 )->update( array( 'title' => 'Test Category' ) );

		$db->commit();

		$this->assertEquals( array(
			"SELECT * FROM `category` WHERE id > 21 LIMIT 2",
			"UPDATE `category` SET `title` = 'Test Category' WHERE `id` IN ( '22', '23' )",
		), $this->queries );

	}

	function testDelete() {

		$db = self::$db;

		$db->begin();

		$db->dummy()->delete();
		$db->dummy()->where( 'test', 1 )->delete();

		$queries = $this->queries;
		$db->dummy()->insert( array( 'id' => 1, 'test' => 44 ) );
		$db->dummy()->insert( array( 'id' => 2, 'test' => 42 ) );
		$db->dummy()->insert( array( 'id' => 3, 'test' => 45 ) );
		$db->dummy()->insert( array( 'id' => 4, 'test' => 47 ) );
		$db->dummy()->insert( array( 'id' => 5, 'test' => 48 ) );
		$db->dummy()->insert( array( 'id' => 6, 'test' => 43 ) );
		$db->dummy()->insert( array( 'id' => 7, 'test' => 41 ) );
		$db->dummy()->insert( array( 'id' => 8, 'test' => 46 ) );
		$this->queries = $queries;

		$db->commit();

		$db->begin();
		$db->dummy()->where( 'test > 42' )->limit( 2, 2 )->delete();
		$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->limit( 2 )->delete();
		$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->delete();
		$db->commit();

		$this->assertEquals( array(
			"DELETE FROM `dummy`",
			"DELETE FROM `dummy` WHERE `test` = '1'",
			"SELECT * FROM `dummy` WHERE test > 42 LIMIT 2 OFFSET 2",
			"DELETE FROM `dummy` WHERE `id` IN ( '4', '5' )",
			"SELECT * FROM `dummy` WHERE test > 42 ORDER BY `test` ASC LIMIT 2",
			"DELETE FROM `dummy` WHERE `id` IN ( '6', '1' )",
			"DELETE FROM `dummy` WHERE test > 42"
		), $this->queries );

	}

	function testDeletePrimary() {

		$db = self::$db;

		$db->begin();

		$db->category()->where( 'id > 21' )->limit( 2 )->delete();

		$db->commit();

		$this->assertEquals( array(
			"SELECT * FROM `category` WHERE id > 21 LIMIT 2",
			"DELETE FROM `category` WHERE `id` IN ( '22', '23' )",
		), $this->queries );

	}

	function testDeleteComposite() {

		$db = self::$db;

		$db->begin();

		$db->categorization()->where( 'category_id > 21' )->limit( 2 )->delete();

		$db->commit();

		$this->assertEquals( array(
			"SELECT * FROM `categorization` WHERE category_id > 21 LIMIT 2",
			"DELETE FROM `categorization` WHERE ( `category_id` = '22' AND `post_id` = '11' ) OR ( `category_id` = '23' AND `post_id` = '11' )",
		), $this->queries );

	}

	function testWhere() {

		$db = self::$db;

		$db->dummy()->where( 'test', null )->fetch();
		$db->dummy()->where( 'test', 31 )->fetch();
		$db->dummy()->whereNot( 'test', null )->fetch();
		$db->dummy()->whereNot( 'test', 31 )->fetch();
		$db->dummy()->where( 'test', array( 1, 2, 3 ) )->fetch();
		$db->dummy()->where( 'test = 31' )->fetch();
		$db->dummy()->where( 'test = ?', 31 )->fetch();
		$db->dummy()->where( 'test = ?', array( 31 ) )->fetch();
		$db->dummy()->where( 'test = :param', array( 'param' => 31 ) )->fetch();
		$db->dummy()
			->where( 'test < :a', array( 'a' => 31 ) )
			->where( 'test > :b', array( 'b' => 0 ) )
			->fetch();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE `test` IS NULL",
			"SELECT * FROM `dummy` WHERE `test` = '31'",
			"SELECT * FROM `dummy` WHERE `test` IS NOT NULL",
			"SELECT * FROM `dummy` WHERE `test` != '31'",
			"SELECT * FROM `dummy` WHERE `test` IN ( '1', '2', '3' )",
			"SELECT * FROM `dummy` WHERE test = 31",
			"SELECT * FROM `dummy` WHERE test = ?",
			"SELECT * FROM `dummy` WHERE test = ?",
			"SELECT * FROM `dummy` WHERE test = :param",
			"SELECT * FROM `dummy` WHERE test < :a AND test > :b",
		), $this->queries );

		$this->assertEquals( array(
			array(),
			array(),
			array(),
			array(),
			array(),
			array(),
			array( 31 ),
			array( 31 ),
			array( 'param' => 31 ),
			array( 'a' => 31, 'b' => 0 ),
		), $this->params );

	}

	function testOrderBy() {

		$db = self::$db;

		$db->dummy()->orderBy( 'id', 'DESC' )->orderBy( 'test' )->fetch();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` ORDER BY `id` DESC, `test` ASC",
		), $this->queries );

	}

	function testLimit() {

		$db = self::$db;

		$db->dummy()->limit( 3 )->fetch();
		$db->dummy()->limit( 3, 10 )->fetch();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` LIMIT 3",
			"SELECT * FROM `dummy` LIMIT 3 OFFSET 10",
		), $this->queries );

	}

	function testSelect() {

		$db = self::$db;

		$db->dummy()->select( 'test' )->fetch();
		$db->dummy()->select( 'test', 'id' )->fetch();

		$this->assertEquals( array(
			"SELECT test FROM `dummy`",
			"SELECT test, id FROM `dummy`",
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

		foreach ( $db->post()->orderBy( 'date_published', 'DESC' ) as $post ) {

			$author = $post->author()->fetch();
			$editor = $post->editor()->fetch();
			$editor2 = $post->editor( 'id > ?', 0 )->fetch();

			if ( $author ) $this->assertTrue( $author->exists() );
			if ( $editor ) $this->assertTrue( $editor->exists() );

			$t = array();

			$t[ 'title' ] = $post->title;
			$t[ 'author' ] = $author->name;
			$t[ 'editor' ] = $editor ? $editor->name : null;
			$t[ 'categories' ] = array();

			foreach ( $post->categorizationList()->category() as $category ) {

				$t[ 'categories' ][] = $category->title;

			}

			$post->categorizationList()->category( 'id > ?', 0 )->fetchAll();

			$posts[] = $t;

		}

		$this->assertEquals( array(
			"SELECT * FROM `post` ORDER BY `date_published` DESC",
			"SELECT * FROM `user` WHERE `id` IN ( '2', '1' )",
			"SELECT * FROM `user` WHERE `id` IN ( '3', '2' )",
			"SELECT * FROM `user` WHERE id > ? AND `id` IN ( '3', '2' )",
			"SELECT * FROM `categorization` WHERE `post_id` IN ( '13', '11', '12' )",
			"SELECT * FROM `category` WHERE `id` IN ( '22', '23', '21' )",
			"SELECT * FROM `category` WHERE id > ? AND `id` IN ( '22', '23', '21' )"
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

	function testCountResultIsAnInteger() {

		$db = self::$db;

		$expected = count( $db->user()->fetchAll() );
		$result = $db->user()->count();
		$this->assertSame( $expected, $result );

	}

	function testJsonSerialize() {

		// only supported for PHP >= 5.4.0
		if ( version_compare( phpversion(), '5.4.0', '<' ) ) return;

		$db = self::$db;

		$json = json_encode( $db->user()->select( 'id' ) );
		$expected = '[{"id":"1"},{"id":"2"},{"id":"3"}]';
		$this->assertEquals( $expected, $json );

	}

	/**
	 * @expectedException \LogicException
	 * @expectedExceptionMessage "post_id" does not exist in "user" result
	 */
	function testBadReference() {

		$db = self::$db;

		$db->user()->post()->fetchAll();

	}

	function testCreateRow() {

		$db = self::$db;

		$row = $db->user()->createRow( array( 'name' => 'foo' ) );

		$this->assertTrue( $row instanceof \LessQL\Row );
		$this->assertSame( 'user', $row->getTable() );

		$row->save();

		$row = $db->user( $row[ 'id' ] );

		$this->assertSame( 'foo', $row[ 'name' ] );

	}

}
