# Tandoor House

Ordering, bookings, kitchen display and back office for a UK restaurant, built with Laravel 13, Inertia, React and TypeScript.

## Requirements
PHP 8.3+, Composer, Node 22, SQLite (local) or MySQL (production).

## Setup
```bash
composer setup
php artisan migrate:fresh --seed
composer dev
```
With Laravel Herd the site is served at http://tandoor-house.test.

## Demo logins
Every demo account uses the password `password`: `owner@`, `manager@`, `cashier@`, `waiter@`, `kitchen@`, `rider@` and `customer@tandoorhouse.test`.

## Checks
```bash
composer ci:check
```
Runs the linter and formatter, TypeScript, Pint, PHPStan and the Pest suite.

## Configuration
The restaurant's name, cuisine and contact details live in `config/restaurant.php` and can be set from `.env` (`RESTAURANT_NAME`, `RESTAURANT_CUISINE`, ...).

## Documentation
See `docs/plan.md` for the product plan and `docs/decisions.md` for the reasoning behind it.
