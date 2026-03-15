# AGENT.md

## Project Identity

`laravel-auto-crud-api` is a reusable Laravel package that generates CRUD routes and provides a generic controller plus a dynamic query builder for Eloquent resources.

This is a package repository, not an application. Think in terms of public API stability, configuration compatibility, route safety, and framework-version support.

## Stack

- PHP `^8.1`
- Laravel component support for `^10.0 | ^11.0 | ^12.0`
- Package service provider + facade
- PHPUnit 10 and Orchestra Testbench declared in `composer.json`

## Start Here

Read these files first before changing behavior:

1. `src/Providers/AutoCrudServiceProvider.php`
2. `src/Services/AutoRouteGeneratorService.php`
3. `src/Services/GenericQueryBuilderService.php`
4. `src/Controllers/AutoCrudController.php`
5. `config/auto-crud.php`
6. `src/Commands/GenerateRoutesCommand.php`
7. `src/Commands/ResetRoutesCommand.php`
8. `README.md`
9. `ROUTE_CONFLICT_PREVENTION.md`
10. `COMMANDS_REFERENCE.md`

## Essential Commands

```bash
composer install
composer validate --no-check-publish
php artisan vendor:publish --tag=auto-crud-config
php artisan auto-crud:generate-routes --validate
php artisan auto-crud:generate-routes --dry-run
php artisan auto-crud:generate-routes --scan --directory="app/Models"
php artisan auto-crud:reset-routes --show
```

Practical note:

- `composer validate --no-check-publish` succeeds in the current workspace.
- `composer.json` declares PHPUnit and Testbench, but no `tests/` directory is currently present in this checkout.

## Architecture

The package has three main responsibilities.

1. Register services and commands through `AutoCrudServiceProvider`.
2. Generate safe CRUD routes through `AutoRouteGeneratorService`.
3. Execute generic CRUD and query behavior through `AutoCrudController` and `GenericQueryBuilderService`.

The package is configuration-driven. `config/auto-crud.php` is a major part of the public API.

## Hard Project Rules

1. Preserve backward compatibility where possible. This package is meant to be consumed by external Laravel apps.
2. Check whether the behavior is already configurable before adding new code paths.
3. Treat route names, route patterns, and query parameter contracts as public surface area.
4. Keep conflict prevention behavior intact unless the task explicitly changes that contract.
5. Update documentation when route generation or query behavior changes.

## Route Generation Model

Important facts for future agents:

- Auto-generation is disabled by default for safety.
- Conflict prevention is enabled by default.
- Specific routes are intentionally declared before parameterized routes in config.
- Route registration timing matters; inspect the provider before changing boot behavior.
- The package supports dry-run, validation, selective generation, scanning, and reset flows.

If you touch route generation, read both the implementation and the docs files that explain the safety model.

## Query Builder Contract

`src/Services/GenericQueryBuilderService.php` consumes structured query parameters such as:

- `select`
- `filters`
- `orFilters`
- `filtersIn`
- `order`
- `groupBy`
- `relationship`
- `relationshipFilter`
- pagination settings

This contract is part of the package value proposition. Avoid silent breaking changes in accepted shapes or behavior.

## Query Generator Usage

The package query generator is `FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService`. It can be used directly, but the preferred package path is through `AutoCrudController` so that request handling stays consistent.

Direct usage example:

```php
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;

$queryBuilder = new GenericQueryBuilderService($request, User::class);
$items = $queryBuilder->getCollections();
$page = $queryBuilder->getCollectionsPaginated();
$item = $queryBuilder->getOne();
```

Accepted query parameter shapes in the current implementation:

- `select`: JSON array of selected columns, example `select=["id","email"]`
- `filters`: JSON array of AND conditions, example `filters=[["status","=","active"]]`
- `orFilters`: JSON array of OR conditions, example `orFilters=[["name","like","%john%"]]`
- `filtersIn`: either one object `{"field":"id","values":[1,2]}` or an array of such objects
- `order`: JSON object such as `{"field":"created_at","order":"desc"}`
- `groupBy`: JSON array of columns
- `relationship`: JSON array of relationship definitions, optionally with nested `query`
- `relationshipFilter`: JSON array of `whereHas` filters
- relationship pagination: inside `relationship[].query.paginate`, for example `{"key":"posts","query":{"order":{"field":"id","order":"desc"},"paginate":{"per_page":10,"page":2}}}`

Behavior to preserve:

1. JSON decoding is validated and throws `InvalidArgumentException` on malformed input.
2. `filtersIn` supports both legacy single-object format and multi-whereIn array format.
3. `relationship` can apply nested `filters`, `orFilters`, `filtersIn`, `order`, `groupBy`, and `select`.
4. Relationship pagination is applied after the base models are fetched and replaces the relation with a paginator.
5. `per_page` is bounded by package config defaults and maximums.

Extension guidance:

1. Prefer adding new query options in a backward-compatible way.
2. Keep request parsing, bounds checking, and docs synchronized.
3. If a new parameter changes public behavior, update `README.md` and command/reference docs in the same task.

## Coding Rules

Use these rules for all changes in this package.

1. Treat configuration keys, route generation behavior, and query parameter shapes as public API.
2. Prefer additive, backward-compatible changes over renames or semantic rewrites.
3. Keep framework-specific assumptions minimal so support for Laravel 10, 11, and 12 remains intact.
4. Place reusable behavior in services or controller hooks rather than scattering logic across commands.
5. When extending the query builder, preserve validation, config-driven limits, and existing legacy formats.
6. Update docs when package behavior changes; documentation is part of the deliverable here.
7. Avoid app-specific conventions in package code unless they are explicitly configurable.
8. Keep command UX safe by preserving validate, dry-run, and reset workflows when touching route generation.

## Hooks And Extension Points

`AutoCrudController` supports custom hooks and preprocessing/postprocessing behavior.

- authorization hooks
- preprocess hooks
- postprocess hooks
- bulk insert preprocessing
- pre-delete behavior
- many-to-many synchronization through `*_ids` payload keys

When extending these hooks, preserve controller ergonomics and keep the default path simple.

## Key Files By Concern

- Service provider: `src/Providers/AutoCrudServiceProvider.php`
- Route engine: `src/Services/AutoRouteGeneratorService.php`
- Query engine: `src/Services/GenericQueryBuilderService.php`
- Base controller: `src/Controllers/AutoCrudController.php`
- Config contract: `config/auto-crud.php`
- Generation command: `src/Commands/GenerateRoutesCommand.php`
- Reset command: `src/Commands/ResetRoutesCommand.php`
- Examples: `examples/AdvancedExamples.php` and `src/Examples/`
- Package overview: `README.md`
- Route safety docs: `ROUTE_CONFLICT_PREVENTION.md`, `ROUTE_MANAGEMENT.md`, `ROUTE_RESET_FIX.md`

## Safe Change Strategy

1. Identify whether the requested feature belongs in config, command UX, route generation, controller behavior, or query parsing.
2. Review the docs files before changing implementation because many edge cases are already documented.
3. Avoid changing default route shapes unless explicitly required.
4. If you extend query parsing, keep validation and bounds checks aligned with config.
5. If you add a feature, document it in README and command reference files.

## Common Pitfalls

- Treating this package like an app and making app-specific assumptions.
- Breaking route conflict prevention by reordering or widening route patterns.
- Modifying config keys without considering downstream consumers.
- Forgetting that docs are part of the product here; implementation-only changes are incomplete.
- Assuming test coverage exists locally; the package declares testing dependencies but currently lacks checked-in tests.

## Validation Guidance

- Use `composer validate --no-check-publish` for metadata sanity.
- For behavioral changes, test route generation in a host Laravel app or package test harness.
- Prefer dry-run and validate modes before relying on generated routes.

## Do Not Ignore

- `config/auto-crud.php` is effectively a public API.
- Documentation files in the repo are not noise; they capture expected package behavior and edge-case handling.