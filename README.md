# LessQL

LessQL is a thin but powerful data access layer for SQL databases using PDO (PHP Data Objects).
It provides an intuitive API for efficient traversal of related database tables.

Inspired mainly by NotORM, it was written from scratch to provide a
clean API and simplified concepts.

http://lessql.morrisbrodersen.de


## Features

- Traverse related tables with a minimal amount of queries
- Save related structures with one method call
- Convention over configuration
- Work closely to your database: LessQL is not an ORM
- Does not attempt to analyze the database, instead relies on conventions and minimal user hints
- Focus on readable source code so forks and extensions are easier to develop
- Fully tested with MySQL and SQLite3
- MIT license

For full documentation and examples, see the [homepage](http://lessql.morrisbrodersen.de).


## Quick Tour

Traversing related tables efficiently is a killer feature.
The following example only needs four queries (one for each table) to retrieve the data:

```php
$pdo = new \PDO( 'sqlite:blog.sqlite3' );
$db = new \LessQL\Database( $pdo );

foreach ( $db->post()->where( 'is_published', 1 )
		->order( 'date_published', 'DESC' ) as $post ) {

	$author = $post->author()->fetch();

	foreach ( $post->categorizationList()->category() as $category ) {

		// ...

	}

	// ...

}
```

Saving is also a breeze. Row objects can be saved with all its associated structures in one call.
For instance, you can create a `Row` from a plain array and save it:

```php
$row = $db->createRow( 'user', array(
	'name' => 'GitHub User',
	'address' => array(
		'location' => 'Berlin',
		'street' => '...'
	)
);

$row->save(); // creates a user, an address and connects them via user.address_id
```


## Status

LessQL has not been used in production yet, but is fully tested.
It is therefore released as a beta.
Any feedback or contribution is greatly appreciated.

Please send feedback to mb@morrisbrodersen.de or create an issue here at Github.


## Installation

LessQL requires at least PHP 5.3 and PDO.
The composer package name is `morris/lessql`.
You can also download or fork the repository.


## Tests

Run `composer update` in the `lessql` directory.
This will install development dependencies like PHPUnit.
Run the tests with `vendor/bin/phpunit tests`.
