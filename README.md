# MCC Cricket Club Management System

This is the CodeIgniter 4 application foundation for MCC. Domain modules are intentionally not present yet.

## Structure

- `app/Controllers`, `app/Models`, `app/Services`, `app/Libraries`, `app/Helpers`, and `app/Filters` contain shared application code.
- `app/Modules` is reserved for bounded MCC domains. Each module may own `Config/Routes.php`, `Controllers`, `Models`, `Services`, and `Views`.
- `app/Views` contains shared views and future layouts/partials.
- `app/Database/Migrations` and `app/Database/Seeds` are the application schema/data entry points.
- `public/assets` contains browser assets; `writable` contains runtime-generated files.

Install dependencies with `composer install`, copy `.env.example` to `.env`, and configure the database before running the application.

## Current gate

The project is `FOUNDATION-ONLY`. Business modules, business migrations, and business acceptance tests must not be started until the client decisions in `docs/DECISIONS.md` are approved. See `docs/CLIENT-DECISION-WORKSHOP.md` for the workshop checklist.

Generic foundation tests are under `tests/Unit`. Run `composer test` after dependencies are installed. No test result is implied by the presence of the test files.
