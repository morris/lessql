# LessQL

[![Build Status](https://travis-ci.org/morris/lessql.svg?branch=master)](https://travis-ci.org/morris/lessql)
[![Test Coverage](https://codeclimate.com/github/morris/lessql/badges/coverage.svg)](https://codeclimate.com/github/morris/lessql/coverage)
[![Join the chat at https://gitter.im/morris/lessql](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/morris/lessql?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

LessQL is a lightweight and performant alternative to Object-Relational Mapping for PHP.

### [LessQL.net](http://lessql.net)


## Usage

```php
// SCHEMA
// user: id, name
// post: id, title, body, date_published, is_published, user_id
// categorization: category_id, post_id
// category: id, title

// Connection
$pdo = new PDO( 'sqlite:blog.sqlite3' );
$db = new LessQL\Database( $pdo );

// Find posts, their authors and categories efficiently:
// Eager loading of references happens automatically.
// This example only needs FOUR queries, one for each table.
$posts = $db->post()
	->where( 'is_published', 1 )
	->orderBy( 'date_published', 'DESC' );

foreach ( $posts as $post ) {
	$author = $post->user()->fetch();

	foreach ( $post->categorizationList()->category() as $category ) {
		// ...
	}
}

// Saving complex structures is easy
$row = $db->createRow( 'post', array(
	'title' => 'News',
	'body' => 'Yay!',
	'categorizationList' => array(
		array(
			'category' => array( 'title' => 'New Category' )
		),
		array( 'category' => $existingCategoryRow )
	)
);

// Creates a post, a new category, two new categorizations
// and connects them all correctly.
$row->save();
```


## Installation

Install LessQL via composer: `composer require morris/lessql`.
LessQL requires PHP >= 5.3.0 and PDO.


## Features

- Efficient deep finding through intelligent eager loading
- Constant number of queries, no N+1 problems
- Save complex, nested structures with one method call
- Convention over configuration
- Work closely to your database: LessQL is not an ORM
- No glue code required
- Clean, readable source code
- Fully tested with SQLite3, MySQL and PostgreSQL
- MIT license

Inspired mainly by NotORM, it was written from scratch to provide a clean API and simplified concepts.

### For full documentation and examples, see the [homepage](http://lessql.net).
