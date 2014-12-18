<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class RowTest extends BaseTest {

	function testAccess() {

		$db = self::$db;

		$row = $db->createRow( 'user', array( 'name' => 'Foo Bar' ) );

		$row[ 'bar' ] = 1;
		$row->baz = 2;

		$row->setData( array( 'zip' => 'zap' ) );

		$a = array(
			$row[ 'name' ],
			$row->name,
			$row[ 'id' ],
			$row->id,
			$row->bar,
			$row->baz
		);

		$ex = array(
			'Foo Bar',
			'Foo Bar',
			null,
			null,
			1,
			2
		);

		$this->assertEquals( $ex, $a );

		// iterator

		$a = array();

		foreach ( $row as $key => $value ) {

			$a[ $key ] = $value;

		}

		$ex = $row->getData();

		$this->assertEquals( $ex, $a );

	}

	function testClean() {

		$db = self::$db;

		$row = $db->createRow( 'user', array( 'id' => 42, 'name' => 'Foo Bar' ) );

		$this->assertEquals( array( 'name' => 'Foo Bar', 'id' => 42 ), $row->getModified() );
		$this->assertEquals( null, $row->getOriginalid() );
		$this->assertEquals( false, $row->isClean() );

		$row->setClean();

		$this->assertEquals( array(), $row->getModified() );
		$this->assertEquals( 42, $row->getOriginalId() );
		$this->assertEquals( true, $row->isClean() );

	}

	/**
	 * @expectedException \LogicException
	 */
	function testCleanEx() {

		$db = self::$db;

		$row = $db->createRow( 'user', array( 'name' => 'Foo Bar' ) );
		$row->setClean();

	}

	function testId() {

		$db = self::$db;

		$row = $db->createRow( 'user', array( 'id' => 42, 'name' => 'Foo Bar' ) );

		$a = array(
			$row[ 'id' ],
			$row->id,
			$row->getId(),
			$row->getOriginalId()
		);

		$row->setClean();

		$a[] = $row->getOriginalId();

		$ex = array(
			42,
			42,
			42,
			null,
			42
		);

		$this->assertEquals( $ex, $a );

	}

	function testCompoundId() {

		$db = self::$db;

		$row = $db->createRow( 'categorization' );

		$a[] = $row->getId();

		$row[ 'category_id' ] = 1;

		$a[] = $row->getId();

		$row[ 'post_id' ] = 2;

		$a[] = $row->getId();

		$ex = array(
			null,
			null,
			array( 'category_id' => 1, 'post_id' => 2 )
		);

		$this->assertEquals( $ex, $a );

	}

	function testData() {

		$db = self::$db;

		$row = $db->createRow( 'post', array(
			'title' => 'Fantasy Movie Review',
			'user' => array(
				'name' => 'Fantasy Guy'
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

		$this->assertEquals( array( 'title' => 'Fantasy Movie Review' ), $row->getData() );
		$this->assertEquals( array( 'title' => 'Fantasy Movie Review' ), $row->getModified() );

	}

	function testDelete() {

		$db = self::$db;

		$row = $db->createRow( 'user', array( 'id' => 42, 'name' => 'Foo Bar' ) );

		$row->delete(); // does nothing

		$row->setClean();
		$row->delete();

		$this->assertEquals( array( "DELETE FROM `user` WHERE `id` = 42" ), $this->queries );

	}

	function testSave() {

		$db = self::$db;

		$row = $db->createRow( 'post', array(
			'title' => 'Fantasy Movie Review',
			'author' => array(
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
			"INSERT INTO `post` ( `title`, `author_id`, `editor_id` ) VALUES ( 'Fantasy Movie Review', NULL, NULL )",
			"INSERT INTO `user` ( `name` ) VALUES ( 'Fantasy Guy' )",
			"INSERT INTO `user` ( `name`, `post_id` ) VALUES ( 'Big Boss', NULL )",
			"INSERT INTO `post` ( `title` ) VALUES ( 'Favorite Post' )",
			"INSERT INTO `category` ( `title` ) VALUES ( 'Movies' )",
			"INSERT INTO `category` ( `title` ) VALUES ( 'Fantasy' )",
			"UPDATE `post` SET `author_id` = '4', `editor_id` = '5' WHERE `id` = '14'",
			"UPDATE `user` SET `post_id` = '15' WHERE `id` = '5'",
			"INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '24' )",
			"INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '25' )"
		), $this->queries );

	}

	function testJsonSerialize() {

		$db = self::$db;

		$data = array(
			'title' => 'Fantasy Movie Review',
			'published' => new \DateTime( '2014-01-01 01:00:00' ),
			'author' => array(
				'name' => 'Fantasy Guy'
			),
			'categorizationList' => array(
				array(
					'category' => array( 'title' => 'Movies' )
				),
				array(
					'category' => array( 'title' => 'Fantasy' )
				)
			)
		);

		$row = $db->createRow( 'post', $data );

		$a = $row->jsonSerialize();
		$ex = $data;
		$ex[ 'published' ] = $data[ 'published' ]->format( 'Y-m-d H:i:s' );

		$this->assertEquals( $ex, $a );

	}

	//

	function testReferenced() {

		$db = self::$db;

		$post = $db->post( 11 );

		$author = $post->author()->fetch();
		$categorizations = $post->categorizationList()->fetchAll();

		$this->assertEquals( 1, $author->id );
		$this->assertEquals( 2, count( $categorizations ) );

		$this->assertEquals( array(
			"SELECT * FROM `post` WHERE `id` = 11",
			"SELECT * FROM `user` WHERE `id` = '1'",
			"SELECT * FROM `categorization` WHERE `post_id` = '11'",
		), $this->queries );

	}

}
