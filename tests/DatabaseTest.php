<?php

require_once 'vendor/autoload.php';
require_once 'TestBase.php';

class DatabaseTest extends TestBase
{
    public function testTable()
    {
        $db = self::$db;

        $result1 = $db->user();
        $result2 = $db->table('user');

        $row1 = $db->user(1);
        $row2 = $db->table('user', 2);

        $ex = array('user', 'user', 'user', 'user', 1, 2);
        $a = array(
            $result1->getTable(),
            $result2->getTable(),
            $row1->getTable(),
            $row2->getTable(),
            $row1['id'],
            $row2['id']
        );

        $this->assertEquals($ex, $a);
    }

    public function testCreateRow()
    {
        $db = self::$db;

        $row = $db->createRow('dummy', array('foo' => 'bar'), 'test');

        $this->assertEquals($row->getTable(), 'dummy');
        $this->assertEquals($row->foo, 'bar');
        $this->assertEquals($row->getResult(), 'test');
    }

    public function testCreateResult()
    {
        $db = self::$db;

        $row = $db->createResult($db, 'dummy');

        $this->assertEquals($row->getTable(), 'dummy');
        $this->assertEquals($row->getParent(), null);
    }

    public function testPDO()
    {
        $db = self::$db;

        $statement = $db->prepare('SELECT * FROM user');
        $this->assertInstanceOf('\PDOStatement', $statement);

        $db->lastInsertId();

        $db->begin();
        $db->rollback();

        $db->begin();
        $db->commit();
    }

    public function testHints()
    {
        $db = self::$db;

        $db->setAlias('alias', 'foo');
        $db->setPrimary('foo', 'fid');
        $db->setPrimary('bar', array('x', 'y'));
        $db->setReference('bar', 'foo', 'fid');
        $db->setBackReference('foo', 'bar', 'fid');
        $db->setRequired('foo', 9);
        $db->setRequired('foo', 10);
        $db->setSequence('foo', 'fooseq');

        $a = array(
            $db->getAlias('alias'),
            $db->getPrimary('foo'),
            $db->getPrimary('bar'),
            $db->getReference('bar', 'foo'),
            $db->getBackReference('foo', 'bar'),
            $db->isRequired('foo', 9),
            $db->isRequired('foo', 10),
            $db->getRequired('foo'),
            $db->getSequence('foo'),
            $db->getSequence('baz')
        );

        $ex = array(
            'foo',
            'fid',
            array('x', 'y'),
            'fid',
            'fid',
            true,
            true,
            array(9 => true, 10 => true),
            'fooseq',
            'baz_id_seq'
        );

        $this->assertEquals($ex, $a);
    }

    public function testRewrite()
    {
        $db = self::$db;

        $db->setRewrite(function ($table) {
            return 'dummy';
        });

        try {
            $db->begin();
            $db->post()->fetchAll();
            $db->user()->insert(array('test' => 42));
            $db->category()->update(array('test' => 42));
            $db->post()->delete();
            $db->user()->sum('test');
            $db->commit();
        } catch (\PDOException $ex) {
            $db->rollback();
        }

        $db->setRewrite(null);

        $this->assertEquals(array(
            "SELECT * FROM `dummy`",
            "INSERT INTO `dummy` (`test`) VALUES ('42')",
            "UPDATE `dummy` SET `test` = '42'",
            "DELETE FROM `dummy`",
            "SELECT SUM(test) FROM `dummy`",
        ), $this->queries);
    }

    public function testIs()
    {
        $db = self::$db;

        $d = $db->getIdentifierDelimiter();
        $db->setIdentifierDelimiter('`');

        $a = array(
            $db->is('foo', null),
            $db->is('foo', 0),
            $db->is('foo', 'bar'),
            $db->is('foo', new \DateTime('2015-01-01 01:00:00')),
            $db->is('foo', $db->literal("BAR")),
            $db->is('foo', array('x', 'y')),
            $db->is('foo', array('x', null)),
            $db->is('foo', array('x')),
            $db->is('foo', array()),
            $db->is('foo', array( null)),
        );

        $db->setIdentifierDelimiter($d);

        $ex = array(
            "`foo` IS NULL",
            "`foo` = '0'",
            "`foo` = 'bar'",
            "`foo` = '2015-01-01 01:00:00'",
            "`foo` = BAR",
            "`foo` IN ('x', 'y')",
            "`foo` IN ('x') OR `foo` IS NULL",
            "`foo` = 'x'",
            "0=1",
            "`foo` IS NULL",
        );

        $this->assertEquals($ex, $a);
    }

    public function testIsNot()
    {
        $db = self::$db;

        $d = $db->getIdentifierDelimiter();
        $db->setIdentifierDelimiter('`');

        $a = array(
            $db->isNot('foo', null),
            $db->isNot('foo', 0),
            $db->isNot('foo', 'bar'),
            $db->isNot('foo', new \DateTime('2015-01-01 01:00:00')),
            $db->isNot('foo', $db->literal("BAR")),
            $db->isNot('foo', array('x', 'y')),
            $db->isNot('foo', array('x', null)),
            $db->isNot('foo', array('x')),
            $db->isNot('foo', array()),
            $db->isNot('foo', array( null)),
        );

        $db->setIdentifierDelimiter($d);

        $ex = array(
            "`foo` IS NOT NULL",
            "`foo` != '0'",
            "`foo` != 'bar'",
            "`foo` != '2015-01-01 01:00:00'",
            "`foo` != BAR",
            "`foo` NOT IN ('x', 'y')",
            "`foo` NOT IN ('x') AND `foo` IS NOT NULL",
            "`foo` != 'x'",
            "1=1",
            "`foo` IS NOT NULL",
        );

        $this->assertEquals($ex, $a);
    }

    public function testQuote()
    {
        $db = self::$db;

        $a = array(
            $db->quote(null),
            $db->quote(false),
            $db->quote(true),
            $db->quote(0),
            $db->quote(1),
            $db->quote(0.0),
            $db->quote(3.1),
            $db->quote('1'),
            $db->quote('foo'),
            $db->quote(''),
            $db->quote($db->literal('BAR')),
        );

        $ex = array(
            "NULL",
            "'0'",
            "'1'",
            "'0'",
            "'1'",
            "'0'",
            "'3.1'",
            "'1'",
            "'foo'",
            "''",
            "BAR",
        );

        $this->assertEquals($ex, $a);
    }

    public function testQuoteIdentifier()
    {
        $db = self::$db;

        $d = $db->getIdentifierDelimiter();
        $db->setIdentifierDelimiter('`');

        $a = array(
            $db->quoteIdentifier('foo'),
            $db->quoteIdentifier('foo.bar'),
            $db->quoteIdentifier('foo`.bar'),
        );

        $db->setIdentifierDelimiter('"');

        $a[] = $db->quoteIdentifier('foo.bar');

        $db->setIdentifierDelimiter($d);

        $ex = array(
            "`foo`",
            "`foo`.`bar`",
            "`foo```.`bar`",
            '"foo"."bar"',
        );

        $this->assertEquals($ex, $a);
    }
}
