# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project: Vispa Platform

Laravel 13 app (PHP 8.3+) using **Filament 4** as the admin/app panel. Domain is a versioned, localizable recipe system. Dev environment runs through Laravel Sail (Docker: MySQL 8.4 + Redis + a PHP runtime container).

## Commands

### Local dev (host PHP)
- `composer dev` — runs `php artisan serve`, `queue:listen`, `pail` (logs), and `npm run dev` concurrently.
- `composer test` — clears config, then `php artisan test` (PHPUnit; `testing` DB and array drivers per `phpunit.xml`).
- Run a single test: `php artisan test --filter=TestName` or `php artisan test tests/Feature/Path/SomeTest.php`.
- Lint: `./vendor/bin/pint` (Laravel Pint).
- Asset build: `npm run build` (Vite + Tailwind 4).

### Sail / Docker
- The active compose file is `compose.yaml` (preferred by Docker Compose over `docker-compose.yml`). It uses the custom `./docker/8.5` runtime and `./docker/mysql/create-testing-database.sh`. App is exposed on `APP_PORT` (`.env` sets `876`); MySQL forwarded on `FORWARD_DB_PORT` (`.env` sets `3376`).
- Start: `./vendor/bin/sail up -d` (or `docker compose up -d`).
- Shell: `./vendor/bin/sail shell`. Inside, use normal `php artisan …` / `composer …` / `npm …`.

### Filament-specific
- Resources/pages/widgets are auto-discovered from `app/Filament/` by `App\Providers\Filament\AppPanelProvider`. The panel is mounted at `/app` with login enabled — you do not need to register new resources manually; just create the file under the right namespace.
- Generate scaffolding: `php artisan make:filament-resource <Model>` (Filament 4 generates `Pages/`, `Schemas/`, `Tables/` subfolders, matching the pattern used by `Recipes/`, `Ingredients/`, `Units/`).

## Architecture

### Domain model — recipes are versioned, one locale per revision

The recipe domain follows a deliberate split. When touching recipes, keep the layer boundaries:

1. **`Recipe`** — the stable identity (owner, visibility, fork lineage via `forked_from_recipe_id` / `forked_from_revision_id`, `default_locale` as a display fallback). Holds no content.
2. **`RecipeRevision`** — a versioned, **single-locale** snapshot of content. Carries `locale`, `version_number`, `status`, `title`, `description`, `notes`, `servings`, times, `published_at`. Unique constraint is `(recipe_id, locale, version_number)` — each locale is an independent revision track (`en-v1` and `sv-v1` coexist). `UPDATED_AT = null` because revisions are append-only: never mutate a published revision, create a new one. All structured content (`ingredientGroups`, `ingredients`, `instructionSections`, `instructionSteps`) hangs off the revision and is in that revision's locale.
3. **Revision-scoped entities hold their own text** — `RecipeRevisionIngredientGroup.title`, `RecipeRevisionIngredient.preparation_note`, `RecipeRevisionInstructionSection.title`, `RecipeRevisionInstructionStep.instruction_text`, `RecipeRevisionImage.alt_text`. No sibling `*Translation` tables; text is in the revision's locale.

**Where a recipe came from** is split across the two layers on purpose: `recipes.source_url` is stable across every locale and version (and is what "only saved links" filters on), while `recipe_revisions.source_credit` is the prose credit and therefore lives in the revision's locale. Content is optional end-to-end — a revision with a title and nothing else is valid, and `RecipeRevision::isLinkOnly()` names that state. The editor shows a `Callout` for the link and an `EmptyState` per empty tab rather than a blank form.

**Photos** (`recipe_revision_images`) hang off the revision like the rest of the content, on the `public` disk under `recipe-images/` (needs `php artisan storage:link`). `is_cover` is kept unique per revision by a `saved` hook on the model that demotes siblings with a query-builder update (deliberately event-free, so it cannot re-enter). `draftFork()` clones the image rows but shares the stored files, so deleting one revision never orphans another's photo — the flip side is that files are not garbage-collected on delete.

`RecipeLocaleSlug` keeps a recipe's public URL slug per locale, stable across revisions.

`Ingredient` / `Unit` are global catalog entities shared across all recipes/locales. `Ingredient` keeps its `IngredientTranslation` sibling so a single catalog row can be displayed in multiple languages — this is the only translation table left, and it's deliberately scoped to the catalog, not to revision content.

### UUIDs
All domain models use `App\Models\Concerns\HasUuid`, which auto-populates `uuid` on `creating`. The `id` (bigint) is still the FK target internally; `uuid` is for external/public references. Migrations consistently add `$table->uuid('uuid')->unique()` alongside `$table->id()`.

### Filament resource layout
Each resource directory follows Filament 4's split convention:
- `XResource.php` — entry point (model binding, navigation).
- `Pages/` — List/Create/Edit page classes.
- `Schemas/` — form schema(s).
- `Tables/` — table column/filter definitions.

This is the convention the codebase already follows; new resources should mirror it.

### Migrations
Domain migrations are dated `2026_05_24_*` and ordered by FK dependency (recipes → revisions → translations → groups → ingredients → instructions). When adding a new table that participates in the revision graph, slot the migration filename so it runs after its FK targets.

## Conventions & gotchas

- **Two compose files exist** (`compose.yaml` and `docker-compose.yml`). Compose uses `compose.yaml` and warns about the duplicate. Edit `compose.yaml`; `docker-compose.yml` is the leftover Sail default.
- **Append-only revisions**: do not edit a `RecipeRevision` row's content after publish — clone into a new revision instead. This is why `RecipeRevision::UPDATED_AT = null`.
- **One revision = one locale**: do not "translate" a revision by adding fields or sibling rows; create a new `RecipeRevision` with a different `locale` value instead.
- **`draftFork()` must copy everything**: when you add a revision-scoped table, extend `RecipeRevision::draftFork()` to clone it, or editing a published revision will silently drop that content.
- **Test DB**: `phpunit.xml` forces `DB_DATABASE=testing`. The MySQL container creates this DB via `docker/mysql/create-testing-database.sh` at first boot — if tests fail with "unknown database 'testing'" on a fresh volume, ensure that script ran (or `sail down -v` to reset).
- **Queue/cache/session** default to `database` driver in `.env` — tables exist via `0001_01_01_*` migrations. Tests override to `sync`/`array` (see `phpunit.xml`), so don't rely on persisted queue state in tests.
- App locale defaults to `en` (see `APP_LOCALE`), but the domain is locale-aware end-to-end — never assume a single locale when querying revision content.
