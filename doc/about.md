# About

LessQL is heavily inspired by [NotORM](https://www.notorm.com/) which presents a novel, intuitive API to SQL databases. Combined with an efficient implementation, its concepts are very unique to all database layers out there, whether they are ORMs, DBALs or something else.

In contrast to ORM, you work directly with tables and rows. This has several advantages:

- **Write less:** No glue code/objects required. Just work with your database directly.
- **Transparency:** It is always obvious that your application code works with a 1:1 representation of your database.
- **Relational power:** Leverage the relational features of SQL databases, don't hide them using an inadequate abstraction.

For more in-depth opinion why ORM is not always desirable, see:

- http://www.yegor256.com/2014/12/01/orm-offensive-anti-pattern.html
- http://seldo.com/weblog/2011/08/11/orm_is_an_antipattern
- http://en.wikipedia.org/wiki/Object-relational_impedance_mismatch

---

NotORM introduced a unique and efficient solution to database abstraction. However, it does have a few weaknesses:

- The API is not always intuitive: `$result->name` is different from `$result->name()` and more.
- There is no difference between One-To-Many and Many-To-One associations in the API (LessQL uses the List suffix for that).
- There is no advanced save operation for nested structures.
- Defining your database structure is hard (involves sub-classing).
- The source code is very hard to read and understand.

LessQL addresses all of these issues.
