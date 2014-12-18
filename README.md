# LessQL

LessQL is a thin but powerful data access layer for SQL databases using
PDO (PHP Data Objects).

Inspired mainly by NotORM, it was written from scratch to provide a
clean API and simplified concepts.

http://lessql.morrisbrodersen.de

## Features

- Traverse associated structures with a minimal amount of queries
- Save associated structures with one method call
- Convention over configuration
- Work closely to your database: LessQL is not an ORM
- Does not attempt to analyze the database, instead relies on conventions and minimal user hints
- Focus on readable source code so forks and extensions are easier to develop
- Tested with MySQL and SQLite3

For full documentation and examples, see the [homepage](http://lessql.morrisbrodersen.de).


## Quick tour

Finding and traversing over associated data efficiently is a killer feature:

```php
$pdo = new \PDO( 'sqlite:blog.sqlite3' );
$db = new \LessQL\Database( $pdo );

foreach ( $db->post()->where( 'is_published', 1 )
		->order( 'date_published', 'DESC' ) as $post ) {

	$author = $post->user()->fetch();

	foreach ( $post->categorizationList()->category() as $category ) {

		// ...

	}

	// ...

}
```

LessQL only needs four queries (one for each table) to get the data in this loop.

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

Structures can be arbitrarily complex, provided there are no fresh
associations with NOT NULL keys that cannot be resolved.


## Requirements

- PHP >= 5.3.0
- PDO


## Installation

The composer package name is `morris/lessql`.
You can also download or fork the repository.


## License

LessQL is licensed under the MIT License. See `LICENSE.md` for details.


## Tests

Run `composer update` in the `lessql` directory.
This will install development dependencies like PHPUnit.
Run the tests with `vendor/bin/phpunit tests`.

