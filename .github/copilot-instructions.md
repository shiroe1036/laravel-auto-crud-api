# Project Guidelines

## Architecture

- This repository is a Laravel package, not an application. Core behavior lives in `src/Services`, `src/Controllers`, `src/Commands`, and `src/Providers`.
- Keep `src/Providers/AutoCrudServiceProvider.php` aligned with package bootstrapping rules, especially the `app->booted()` timing used for route generation.
- Treat `config/auto-crud.php`, `INSTALLATION.md`, and the route-management documents as part of the public surface area of the package.

## Build and Test

- Install dependencies with `composer install`.
- There is no committed test suite in this repository today; if you add tests, use PHPUnit/Testbench conventions already declared in `composer.json`.
- When changes affect package behavior, validate syntax and autoloading at minimum, and prefer testing from a host Laravel app when route generation behavior is involved.

## Conventions

- Preserve the package's safety-first defaults: conflict prevention, explicit route validation, and isolated route naming/prefixes when documenting or changing generation behavior.
- Respect route ordering rules in config and generation logic: specific routes must remain before parameterized `/{id}` routes.
- Prefer extending behavior through configuration and hooks before introducing package-specific special cases in core classes.
- Keep backward compatibility in mind for the declared Laravel support range and update package documentation whenever commands, config keys, or integration flow change.
- Route metadata is cache-backed, so changes to generation or reset flows should consider cleanup and stale-state scenarios.
