# Conventions (and workarounds)

LessQL relies entirely on two conventions:

- Primary key columns should be `id` and
- foreign key columns should be `<table>_id`.

A side effect of these conventions is to use **singular table names**, because plurals are irregular and product.categories_id sounds wrong.

More often than not, these conventions are not enough to work with your database, and workarounds are needed. You will most likely have join tables with compound primary keys. Or you might have two columns in one table pointing to the same foreign table.

LessQL provides solutions for all of these use cases. This section contains real-world examples showing how to define alternate primary keys, assocations and reference keys.

Because SQL is case-insensitive, you should always use `snake_case` for table names, column names, and aliases.

## Required columns

When saving complex structures, LessQL needs a way to know which columns and especially foreign keys are required (`NOT NULL`). This way, rows with required columns are saved last with all their foreign keys set.

Whenever you get "some column may not be null" exceptions, this should solve that.

```php
$db->setRequired('post', 'user_id');
```

## Alternate foreign keys

You can use alternate foreign keys in associations using `via`:

```php
foreach ($db->post() as $post) {
    // single: use post.author_id instead of post.user_id
    $author = $post->user()->via('author_id')->fetch();

    // list: use category.featured_post_id instead of category.post_id
    $featureCategories = $post->categoryList()->via('featured_post_id');
}
```

This is quick and easy, but repetitive. It is often better to define these things globally using one of the following methods.

## Table Aliasing

Let `customer` have columns `address_id` and `billing_address_id`. Both columns point to the `address` table, but the association `billing_address` would try to query the `billing_address` table. For this situation, LessQL lets you define **table aliases**:

```php
$db->setAlias('billing_address', 'address');
$db->customer()->billing_address();
```

This association will use `customer.billing_address_id` as reference key and address as table. Note how we are using the association name, *not* the table name.

## Back References

Consider again the use case from above. Let's try to access the data the other way around: Starting with an address, how would you get users that point to it via `billing_address_id`? The solution is using **back references** and aliases and looks like this:

```php
$db->address()->userList(); // works by convention

$db->setAlias('user_billing', 'user');
$db->setBackReference('address', 'user_billing', 'billing_address_id');
$db->address()->user_billingList();
```

Setting a back reference key in this case states: `address` is referenced by `user_billing` using `billing_address_id`.

## Alternate Primary Keys

Primary keys should be named `id`, but if a primary key goes by a different column name you can override it easily with `$db->setPrimary('user', 'uid')`.

Compound primary keys are also possible and especially useful for join tables: `$db->setPrimary('categorization', ['post_id', 'category_id'])`.

You should always define compound keys manually. Without these definitions, saving nested structures may fail because LessQL cannot know which columns are required for a complete primary key.

## Alternate Reference Keys

Foreign keys should be named `<table>_id`, but if a foreign key goes by a different column name you can override it easily with `$db->setReference('post', 'user', 'uid')`, which basically states the following: post references `user` using the column `uid`.

We're using the term reference to distinguish from back references, as both use foreign keys.

## Table prefixes and other custom table schemes

To add a prefix to your tables, you can define a table rewrite function:

```php
$db->setRewrite(function($table) {
    return 'prefix_' . $table;
});
```

The function is completely arbitrary and will rewrite tables directly before executing any query.
