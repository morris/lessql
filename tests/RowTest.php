<?php

require_once 'vendor/autoload.php';
require_once 'TestBase.php';

class RowTest extends TestBase
{
    public function testAccess()
    {
        $db = self::$db;

        $row = $db->createRow('user', array('name' => 'Foo Bar'));

        $row['bar'] = 1;
        $row->baz = 2;

        $row->setData(array('zip' => 'zap'));

        $a = array(
            $row['name'],
            $row->name,
            $row['id'],
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

        $this->assertEquals($ex, $a);

        // iterator

        $a = array();

        foreach ($row as $key => $value) {
            $a[$key] = $value;
        }

        $ex = $row->getData();

        $this->assertEquals($ex, $a);
    }

    public function testClean()
    {
        $db = self::$db;

        $row = $db->createRow('user', array('id' => 42, 'name' => 'Foo Bar'));

        $this->assertEquals(array('name' => 'Foo Bar', 'id' => 42), $row->getModified());
        $this->assertEquals(null, $row->getOriginalid());
        $this->assertEquals(false, $row->isClean());

        $row->setClean();

        $this->assertEquals(array(), $row->getModified());
        $this->assertEquals(42, $row->getOriginalId());
        $this->assertEquals(true, $row->isClean());
    }

    /**
     * @expectedException \LogicException
     */
    public function testCleanEx()
    {
        $db = self::$db;

        $row = $db->createRow('user', array('name' => 'Foo Bar'));
        $row->setClean();
    }

    public function testId()
    {
        $db = self::$db;

        $row = $db->createRow('user', array('id' => 42, 'name' => 'Foo Bar'));

        $a = array(
            $row['id'],
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

        $this->assertEquals($ex, $a);
    }

    public function testCompoundId()
    {
        $db = self::$db;

        $row = $db->createRow('categorization');

        $a[] = $row->getId();

        $row['category_id'] = 1;

        $a[] = $row->getId();

        $row['post_id'] = 2;

        $a[] = $row->getId();

        $ex = array(
            null,
            null,
            array('category_id' => 1, 'post_id' => 2)
        );

        $this->assertEquals($ex, $a);
    }

    public function testData()
    {
        $db = self::$db;

        $row = $db->createRow('post', array(
            'title' => 'Fantasy Movie Review',
            'user' => array(
                'name' => 'Fantasy Guy'
            ),
            'categorizationList' => array(

                array(
                    'category' => array('title' => 'Movies')
                ),
                array(
                    'category' => array('title' => 'Fantasy')
                )

            )
        ));

        $this->assertEquals(array('title' => 'Fantasy Movie Review'), $row->getData());
        $this->assertEquals(array('title' => 'Fantasy Movie Review'), $row->getModified());
    }

    public function testDelete()
    {
        $db = self::$db;

        $row = $db->createRow('user', array('id' => 42, 'name' => 'Foo Bar'));

        $row->delete(); // does nothing

        $row->setClean();
        $row->delete();

        $this->assertFalse($row->isClean());
        $this->assertFalse($row->exists());
        $this->assertEquals(array("DELETE FROM `user` WHERE `id` = '42'"), $this->queries);
    }

    public function testSave()
    {
        $db = self::$db;

        $row = $db->createRow('post', array(
            'title' => 'Fantasy Movie Review',
            'author' => array(
                'name' => 'Fantasy Guy'
            ),
            'editor' => array(
                'name' => 'Big Boss'
            ),
            'categorizationList' => array(

                array(
                    'category' => array('title' => 'Movies')
                ),
                array(
                    'category' => array('title' => 'Fantasy')
                )

            )
        ));

        $db->begin();
        $row->save();
        $db->commit();

        $this->assertEquals(array(
            "INSERT INTO `post` ( `title`, `author_id`, `editor_id` ) VALUES ( 'Fantasy Movie Review', NULL, NULL )",
            "INSERT INTO `user` ( `name` ) VALUES ( 'Fantasy Guy' )",
            "INSERT INTO `user` ( `name` ) VALUES ( 'Big Boss' )",
            "INSERT INTO `category` ( `title` ) VALUES ( 'Movies' )",
            "INSERT INTO `category` ( `title` ) VALUES ( 'Fantasy' )",
            "UPDATE `post` SET `author_id` = '4', `editor_id` = '5' WHERE `id` = '14'",
            "INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '24' )",
            "INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '25' )"
        ), $this->queries);
    }

    public function testJsonSerialize()
    {
        $db = self::$db;

        $data = array(
            'title' => 'Fantasy Movie Review',
            'date_published' => new \DateTime('2014-01-01 01:00:00'),
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

        $row = $db->createRow('post', $data);

        $a = $row->jsonSerialize();
        $ex = $data;
        $ex['date_published'] = $data['date_published']->format('Y-m-d H:i:s');

        $this->assertEquals($ex, $a);
    }

    public function testReferenced()
    {
        $db = self::$db;

        $post = $db->post(11);

        $this->assertNotNull($post);

        $author = $post->author()->fetch();
        $categorizations = $post->categorizationList()->fetchAll();

        $this->assertEquals(1, $author->id);
        $this->assertEquals(2, count($categorizations));

        $this->assertEquals(array(
            "SELECT * FROM `post` WHERE `id` = '11'",
            "SELECT * FROM `user` WHERE `id` = '1'",
            "SELECT * FROM `categorization` WHERE `post_id` = '11'",
        ), $this->queries);
    }

    public function testReadmeExample()
    {
        $db = self::$db;

        $category = $db->category(21);

        $this->assertNotNull($category);
        $this->assertEquals(21, $category->id);

        $row = $db->createRow('post', array(
            'title' => 'News',

            'categorizationList' => array(
                array(
                    'category' => array('title' => 'New Category')
                ),
                array('category' => $category)
            )
        ));

        // creates a post, two new categorizations, a new category
        // and connects them all correctly
        $row->save();

        $this->assertEquals(array(
            "SELECT * FROM `category` WHERE `id` = '21'",
            "INSERT INTO `post` ( `title` ) VALUES ( 'News' )",
            "INSERT INTO `category` ( `title` ) VALUES ( 'New Category' )",
            "INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '21' )",
            "INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( '14', '24' )",
        ), $this->queries);
    }

    public function testEmptyRow()
    {
        $db = self::$db;

        $row = $db->createRow('dummy');

        $i = 0;

        foreach ($row as $prop) {
            ++$i;
        }

        $this->assertEquals(0, $i);
    }

    public function testHasProperty()
    {
        $db = self::$db;

        $row = $db->createRow('dummy');

        $row['foo'] = 'bar';
        $row['bar'] = null;

        $this->assertTrue($row->hasProperty('foo'));
        $this->assertTrue($row->hasProperty('bar'));
        $this->assertFalse($row->hasProperty('baz'));
    }

    // Test for select without a primary key
    function testKeylessQuery()
    {
        $db = self::$db;
        $row = $db->post()->select('title')->fetch();

        // If key field is absent exist returns False
        $this->assertFalse($row->exists());
        // Row not clean and frozen
        $this->assertFalse($row->isClean());
        $this->assertTrue($row->isFrozen());

    }

    // Test for select with JOIN
    function testJoin()
    {
        $db = self::$db;

        $row = $db->table($db->quoteIdentifier('post').' INNER JOIN '.$db->quoteIdentifier('user').
                        ' ON ('.$db->quoteIdentifier('post.author_id').' = '.
                               $db->quoteIdentifier('user.id').')')->
                               select($db->quoteIdentifier('post.id').' AS id','title','name')->
                               where('post.id',12)->fetch();

        $this->assertEquals(array(
        "SELECT `post`.`id` AS id, title, name FROM `post` INNER JOIN `user` ON (`post`.`author_id` = `user`.`id`) WHERE `post`.`id` = '12'"
        ), $this->queries);

        $this->assertEquals( $row['title'], 'Foo released');
        $this->assertEquals( $row['name'], 'Writer');

        //Query with JOINs mark as Frozen. It cannot be saved or deleted.

        $this->assertFalse($row->exists());
        $this->assertFalse($row->isClean());
        $this->assertTrue($row->isFrozen());

    }

    // Test for select with table join function
    function testJoinCall()
    {
        $db = self::$db;

        $row = $db->table('post')->join('post','author_id', 'user', 'id', 'LEFT')->
                               select($db->quoteIdentifier('post.id').' AS id','title','name')->
                               where('post.id',11)->fetch();

        $this->assertEquals(array(
        "SELECT `post`.`id` AS id, title, name FROM `post` LEFT JOIN `user` ON (`post`.`author_id` = `user`.`id`) WHERE `post`.`id` = '11'"
        ), $this->queries);

        $this->assertEquals( $row['title'], 'Championship won');
        $this->assertEquals( $row['name'], 'Writer');

        $this->assertFalse($row->exists());
        $this->assertFalse($row->isClean());
        $this->assertTrue($row->isFrozen());
    }
}
