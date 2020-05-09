# API

This section covers the public, user-relevant API. There are more methods mainly used for communication between LessQL components. You can always view the source, it is very readable and quite short.

## Setup

Creating a database:

```php
$db = new \LessQL\Database($pdo);
```

Defining schema information (see [Conventions](conventions.md) for usage):

```php
$db->setAlias($alias, $table);
$db->setPrimary($table, $column);
$db->setReference($table, $name, $column);
$db->setBackReference($table, $name, $column);
$db->setRequired($table, $column);
$db->setRewrite($rewriteFunc);
$db->setIdentifierDelimiter($delimiter); // default is ` (backtick)
```

Set a query callback (e.g. for logging):

```php
$db->setQueryCallback(function($query, $params) { ... });
```

## Basic finding

```php
$result = $db->table_name()
$result = $db->table('table_name')
$row = $result->fetch()      // fetch next row in result
$rows = $result->fetchAll()  // fetch all rows
foreach ($result as $row) { ... }
json_encode($result)       // finds and encodes all rows (requires PHP >= 5.4.0)

// get a row directly by primary key
$row = $db->table_name($id)
$row = $db->table('table_name', $id)
```

## Deep finding Association traversal

```php
$assoc = $result->table_name()       // get one row, reference
$assoc = $result->table_nameList()   // get many rows, back reference
$assoc = $result->referenced('table_name')
$assoc = $result->referenced('table_nameList')

$assoc = $row->table_name()          // get one row, reference
$assoc = $row->table_nameList()      // get many rows, back reference
$assoc = $row->referenced('table_name')
$assoc = $row->referenced('table_nameList')

$assoc = $row->table_name()->via($key); // use alternate foreign key
```

## Where

`WHERE` may also be applied to association results.

```php
$result2 = $result->where($column, null)    // WHERE $column IS NULL
$result2 = $result->where($column, $value)  // WHERE $column = $value (escaped)
$result2 = $result->where($column, $array)  // WHERE $column IN $array (escaped)
    // $array containing null is respected with OR $column IS NULL

$result2 = $result->whereNot($column, null)    // WHERE $column IS NOT NULL
$result2 = $result->whereNot($column, $value)  // WHERE $column != $value (escaped)
$result2 = $result->whereNot($column, $array)  // WHERE $column NOT IN $array (escaped)
    // $array containing null is respected with AND $column IS NOT NULL

$result2 = $result->where($whereString, $param1, $param2, ...) // numeric params for PDO
$result2 = $result->where($whereString, $paramArray)           // named and/or numeric params for PDO

$result2 = $result->where($array)    // for each key-value pair, call $result->where($key, $value)
$result2 = $result->whereNot($array) // for each key-value pair, call $result->whereNot($key, $value)
```

## Selected columns, Order and Limit

Note that you can order association results, but you cannot use `LIMIT` on them.

```php
$result2 = $result->select($expr)  // identfiers NOT escaped, so expressions are possible
    // multiple calls are joined with a comma

// $column will be escaped
$result2 = $result->orderBy($column);
$result2 = $result->orderBy($column, 'ASC');
$result2 = $result->orderBy($column, 'DESC');

$result2 = $result->limit($count);
$result2 = $result->limit($count, $offset);
$result2 = $result->paged($pageSize, $page);  // pages start at 1
```

Note that `Result` objects are immutable. All filter methods like where or orderBy return a new Result instance with the new `SELECT` information.

## Aggregation

Aggregation is only supported by basic results. The methods execute the query and return the calculated value directly.

```php
$result->count($expr = '*')   // SELECT COUNT($expr) FROM ...
$result->min($expr)           // SELECT MIN($expr)   FROM ...
$result->max($expr)           // SELECT MAX($expr)   FROM ...
$result->sum($expr)           // SELECT SUM($expr)   FROM ...
$result->aggregate($expr)     // SELECT $expr          FROM ...
```

## Manipulation

```php
$statement = $result->insert($row)   // $row is a data array

// $rows is array of data arrays
// one INSERT per row, slow for many rows
// supports Literals, works everywhere
$statement = $result->insert($rows)

// use prepared PDO statement
// does not support Literals (PDO limitation)
$statement = $result->insert($rows, 'prepared')

// one query with multiple value lists
// supports Literals, but not supported in all PDO drivers (SQLite fails)
$statement = $result->insert($rows, 'batch')

$statement = $result->update($set)   // updates rows matched by the result (UPDATE ... WHERE ...)
$statement = $result->delete()         // deletes rows matched by the result (DELETE ... WHERE ...)
```

## Transactions

```php
$db->begin()
$db->commit()
$db->rollback()
```

## Rows

```php
// create row from scratch
$row = $db->createRow($table, $properties = [])
$row = $db->table_name()->createRow($properties = [])

// get or set properties
$row->property
$row->property = $value
isset($row->property)
unset($row->property)

// array access is equivalent to property access
$row['property']
$row['property'] = $value
isset($row['property'])
unset($row['property'])

$row->setData($array) // sets data on row, extending it

// manipulation
$row->isClean()       // returns true if in sync with database
$row->exists()        // returns true if the row exists in the database
$row->save()          // inserts if not in database, updates changes (only) otherwise
$row->update($data) // set data and save
$row->delete()

// references
$assoc = $row->table_name()         // get one row, reference
$assoc = $row->table_nameList()     // get many rows, back reference
$assoc = $row->referenced('table_name')
$assoc = $row->referenced('table_nameList')

json_encode($row)
foreach ($row as $name => $value) { ... }  // iterate over properties
```
