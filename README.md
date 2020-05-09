# LessQL

[![Build Status](https://travis-ci.org/morris/lessql.svg?branch=master)](https://travis-ci.org/morris/lessql)
[![Test Coverage](https://codeclimate.com/github/morris/lessql/badges/coverage.svg)](https://codeclimate.com/github/morris/lessql/coverage)

LessQL is a lightweight and performant alternative to Object-Relational Mapping for PHP.

**[Guide](doc/guide.md) | [Conventions](doc/conventions.md) | [API Reference](doc/api.md) | [About](doc/about.md)**

*If you are looking for an SQL-based approach superior to raw PDO, check out [DOP](https://github.com/morris/dop) as an alternative.*

## Installation

Install LessQL via composer: `composer require morris/lessql`.
LessQL requires PHP >= 5.6 and PDO.

## Usage

```php
// SCHEMA
// user: id, name
// post: id, title, body, date_published, is_published, user_id
// categorization: category_id, post_id
// category: id, title

// Connection
$pdo = new PDO('sqlite:blog.sqlite3');
$db = new LessQL\Database($pdo);

// Find posts, their authors and categories efficiently:
// Eager loading of references happens automatically.
// This example only needs FOUR queries, one for each table.
$posts = $db->post()
    ->where('is_published', 1)
    ->orderBy('date_published', 'DESC');

foreach ($posts as $post) {
    $author = $post->user()->fetch();

    foreach ($post->categorizationList()->category() as $category) {
        // ...
    }
}

// Saving complex structures is easy
$row = $db->createRow('post', [
    'title' => 'News',
    'body' => 'Yay!',
    'categorizationList' => [
        [
            'category' => ['title' => 'New Category']
        ],
        ['category' => $existingCategoryRow]
    ]
]);

// Creates a post, a new category, two new categorizations
// and connects them all correctly.
$row->save();
```

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

Inspired mainly by [NotORM](https://www.notorm.com/),
it was written from scratch to provide a clean API and simplified concepts.

## Contributors

- [jayaddison](https://github.com/jayaddison)
- [f0ma](https://github.com/f0ma)

Thanks!
