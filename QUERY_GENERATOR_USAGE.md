# Query Generator Usage

## Purpose

`FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService` provides dynamic query construction for package consumers and for controllers built on top of `AutoCrudController`.

Use it when you want a configurable CRUD endpoint that accepts structured JSON query parameters without rewriting Eloquent query parsing for each model.

## Basic Usage

```php
use FivoTech\LaravelAutoCrud\Services\GenericQueryBuilderService;

$queryBuilder = new GenericQueryBuilderService($request, User::class);

$collection = $queryBuilder->getCollections();
$paginated = $queryBuilder->getCollectionsPaginated();
$one = $queryBuilder->getOne();
```

You can also instantiate it first and configure it later:

```php
$queryBuilder = new GenericQueryBuilderService();
$queryBuilder->setRequest($request)->setModel(User::class);
```

## Supported Parameters

### `select`

```text
select=["id","email"]
```

### `filters`

AND conditions.

```text
filters=[["status","=","active"]]
```

### `orFilters`

OR conditions.

```text
orFilters=[["name","like","%john%"]]
```

### `filtersIn`

Two formats are supported.

Single clause format:

```text
filtersIn={"field":"id","values":[1,2,3]}
```

Multiple clause format:

```text
filtersIn=[
  {"field":"id","values":[1,2,3]},
  {"field":"role","values":["admin","teacher"]}
]
```

### `order`

```text
order={"field":"created_at","order":"desc"}
```

### `groupBy`

```text
groupBy=["role"]
```

### `relationship`

Eager load relationships with optional nested query constraints.

```text
relationship=[
  {"key":"profile"},
  {
    "key":"posts",
    "query": {
      "filters": [["status","=","published"]],
      "order": {"field":"id","order":"desc"},
      "select": ["id","user_id","title"]
    }
  }
]
```

Supported nested query keys:

- `filters`
- `orFilters`
- `filtersIn`
- `order`
- `groupBy`
- `select`

### `relationshipFilter`

Filter the parent model through `whereHas(...)`.

```text
relationshipFilter=[
  {
    "relationship":"posts",
    "filters":[["status","=","published"]]
  }
]
```

### Relationship Pagination

The package supports paginating a related collection through relationship query options.

```text
relationship=[
  {
    "key":"posts",
    "query": {
      "order": {"field":"id","order":"desc"},
      "paginate": {"per_page":10,"page":2}
    }
  }
]
```

In that case the relation is replaced with a paginator after the base models are loaded.

## Runtime Behavior

1. Invalid JSON throws `InvalidArgumentException`.
2. `filtersIn` supports both legacy single-object format and multi-clause format.
3. Relationship query options are applied through `applyRelationshipQueryOptions()`.
4. Relationship pagination is applied after the base query result is retrieved.
5. Pagination bounds use package config values such as default and maximum per-page limits.

## Recommended Integration Pattern

1. Keep controller entrypoints thin and delegate query parsing to this service.
2. Treat accepted query keys as public API.
3. If you add a new parameter, update package docs and config behavior together.
4. Preserve backward compatibility for existing consumers.

## Change Rules

When modifying this service:

1. Do not remove or rename accepted keys without an explicit breaking-change decision.
2. Preserve config-driven limits and validation.
3. Update `README.md` and other package docs when behavior changes.
4. Test the change in a host Laravel app or a package test harness before relying on it.