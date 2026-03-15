# AGENT.md

## Mission

This repository is a Laravel package, not an application. Use this file as the reusable operating brief for any AI agent modifying package code, commands, configuration, or documentation.

## Project Snapshot

- Core package logic lives in `src/Services`, `src/Controllers`, `src/Commands`, and `src/Providers`.
- Package bootstrapping is centered on `src/Providers/AutoCrudServiceProvider.php`.
- Public-facing behavior is also defined by `config/auto-crud.php`, `INSTALLATION.md`, and the route-management documents.
- The package focuses on automatic CRUD behavior, route generation, conflict prevention, and dynamic query building.

## Working Rules

- Treat this as a reusable package with backward-compatibility constraints, not as a host Laravel app.
- Preserve safety-first defaults: conflict prevention, explicit route validation, and isolated route prefixes and names.
- Respect route ordering rules. Specific routes must remain ahead of parameterized `/{id}` routes.
- Prefer extending behavior through configuration and hooks before adding package-specific special cases in core services.
- Keep `AutoCrudServiceProvider` aligned with the package boot lifecycle, especially the `app->booted()` timing used for route generation.
- Assume route metadata is cache-backed; generation and reset changes must account for cleanup and stale-state scenarios.
- Update documentation when package behavior, commands, config keys, or integration flow changes.

## Recommended Workflow

1. Identify whether the task affects package internals, public API, configuration, or documentation.
2. Read the relevant service, command, or provider before changing behavior.
3. If route generation is involved, verify conflict prevention, ordering, and reset behavior together.
4. Update the docs that expose the changed behavior before concluding the task.

## Commands

- Install dependencies: `composer install`
- Validate autoloading when needed: `composer dump-autoload`
- Run package tests if they exist locally: `vendor/bin/phpunit`

## Validation Expectations

- There is no committed test suite guaranteed in the repository today, so validate syntax and autoloading at minimum.
- Prefer testing package behavior from a host Laravel application when route generation or bootstrapping behavior changes.
- If documentation or config examples change, keep them aligned with the implemented behavior.

## Common Pitfalls

- Treating the package like an application and coupling code to host-app assumptions.
- Changing route generation without checking conflicts, route order, reset flow, and metadata cleanup together.
- Modifying public behavior without updating the package docs.
- Introducing special-case logic where a configuration or hook-based extension would be more stable.

## Good Task Prompts

- Add a configurable route-generation rule while preserving conflict prevention and backward compatibility.
- Refine a command in `src/Commands` and update the installation or command reference docs accordingly.
- Extend query builder behavior through a hook or config-driven mechanism instead of hardcoded branching.

## Handoff Standard

- State whether the change affects package internals, public API, configuration, or documentation.
- Mention how compatibility, route ordering, and conflict prevention were preserved.
- Report what validation was run locally and whether host-app verification is still needed.