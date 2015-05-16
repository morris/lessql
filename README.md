# LessQL

[![Build Status](https://travis-ci.org/morris/lessql.svg?branch=master)](https://travis-ci.org/morris/lessql)
[![Test Coverage](https://codeclimate.com/github/morris/lessql/badges/coverage.svg)](https://codeclimate.com/github/morris/lessql/coverage)

LessQL is a thin but powerful data access layer for SQL databases using PDO (PHP Data Objects).
It provides an intuitive API for efficient traversal of related database tables.

http://lessql.net

## Usage

```php
// SCHEMA
// user( id, name )
// post( id, title, body, date_published, is_published, author_id )
// categorization( category_id, post_id )
// category( id, title )

$pdo = new \PDO( 'sqlite:blog.sqlite3' );
$db = new \LessQL\Database( $pdo );

$db->setAlias( 'author', 'user' );

foreach ( $db->post()->where( 'is_published', 1 )
		->orderBy( 'date_published', 'DESC' ) as $post ) {

	$author = $post->author()->fetch();

	foreach ( $post->categorizationList()->category() as $category ) {

		// ...

	}

	// ...

}
```

Traversing related tables efficiently is a killer feature.
The example above only needs *four queries* (one for each table) to retrieve the data.

<hr>

Saving is also a breeze. Row objects can be saved with all its associated structures in one call.
For instance, you can create a `Row` from a plain array and save it:

```php
$row = $db->createRow( 'post', array(
	'title' => 'News',
	'body' => 'Yay!',

	'categorizationList' => array(
		array(
			'category' => array( 'title' => 'New Category'
		),
		array( 'category' => $existingCategoryRow )
	)
);

// creates a post, two new categorizations, a new category
// and connects them all correctly
$row->save();
```


## Features

- Traverse related tables with a minimal amount of queries (automatic eager loading)
- Save related structures with one method call
- Convention over configuration
- Work closely to your database: LessQL is not an ORM
- Does not attempt to analyze the database, instead relies on conventions and minimal user hints
- Clean, readable source code so forks and extensions are easy to develop
- Fully tested with SQLite3, MySQL and PostgreSQL
- MIT license

Inspired mainly by NotORM, it was written from scratch to provide a clean API and simplified concepts.

__For full documentation and examples, see the [homepage](http://lessql.net).__

## Status

See `CHANGELOG.md` for details about the releases. If you want to contribute, please do! Feedback is welcome, too.


## Installation

LessQL requires at least PHP 5.3 and PDO.
The composer package name is `morris/lessql`.
You can also download or fork the repository.


## Tests

Run `composer update` in the `lessql` directory.
This will install development dependencies like PHPUnit.
Run the tests with `vendor/bin/phpunit tests`.
