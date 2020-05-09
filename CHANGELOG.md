# Changelog

## 1.0.0

- BREAKING: Drop support for PHP 5.3, 5.4, 5.5
- PSR-4
- Fix #54 (bad where condition generation)
- Fix #45 (bad delete generation)
- Fix #46 (bad float/double escaping)

## v0.4.1

- Added PHP 7 to Travis CI
- PSR-2
- Default to `"` as identifier delimiter for PostgreSQL

## v0.3.4

- Fix `whereNot`
- Fix PHP requirement to 5.3.4

## v0.3.3

- Added `Database/query`

## v0.3.2

- Readme corrections
- Added `Result/createRow`

## v0.3.1

- Fixed uninitialized Row properties
- Added hasProperty method to Row
- Bad single references now throw

## v0.3.0

- Travis CI
- Code climate coverage
- Refactored insert
- Result instances are now immutable (minor API break)
- Readme update

## v0.2.2

- Minor fixes
- Docblock (palicao)
- Fix: count() returns integers now (palicao)

## v0.2.1

- SEMVER

## v0.2-beta

- Improved UPDATE/DELETE behavior
- Improved UPDATE/DELETE tests
- Fixed LIMIT with test
- PostgreSQL support (all tests pass)
- Made tests MySQL-compatible (all tests pass)

## v0.1-beta

- First beta release
