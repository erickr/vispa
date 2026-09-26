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
2. **`RecipeRevision`** — a versioned, **single-locale** snapshot of content. Carries `locale`, `version_number`, `status`, `title`, `description`, `notes`, `servings`, times, `published_at`. Unique constraint is `(recipe_id, locale, version_number)` — each locale is an independent revision track (`en-v1` and `sv-v1` coexist). `UPDATED_AT = null` because revisions were designed to be append-only; editing a published one in place is now allowed, but only as a deliberate choice (see below). All structured content (`ingredientGroups`, `ingredients`, `instructionSections`, `instructionSteps`) hangs off the revision and is in that revision's locale.
3. **Revision-scoped entities hold their own text** — `RecipeRevisionIngredientGroup.title`, `RecipeRevisionIngredient.preparation_note`, `RecipeRevisionInstructionSection.title`, `RecipeRevisionInstructionStep.instruction_text`, `RecipeRevisionImage.alt_text`. No sibling `*Translation` tables; text is in the revision's locale.

**Where a recipe came from** is split across the two layers on purpose: `recipes.source_url` is stable across every locale and version (and is what "only saved links" filters on), while `recipe_revisions.source_credit` is the prose credit and therefore lives in the revision's locale. Content is optional end-to-end — a revision with a title and nothing else is valid, and `RecipeRevision::isLinkOnly()` names that state. The editor shows a `Callout` for the link and an `EmptyState` per empty tab rather than a blank form.

**Photos** (`recipe_revision_images`) hang off the revision like the rest of the content, on the `public` disk under `recipe-images/` (needs `php artisan storage:link`). `is_cover` is kept unique per revision by a `saved` hook on the model that demotes siblings with a query-builder update (deliberately event-free, so it cannot re-enter). `draftFork()` clones the image rows but shares the stored files, so deleting one revision never orphans another's photo — the flip side is that files are not garbage-collected on delete.

**Importing recipes** (`app/Recipes/Import/`): `SafeUrlFetcher` (public hosts only, every redirect checked) → `RecipePageReader` (schema.org JSON-LD if present, else the page's readable text) → `RecipeSource` → `RecipeExtractor` (bound to `ClaudeRecipeExtractor`: structured output, model from `services.anthropic.model`) → `ExtractedRecipe` → `RecipeImporter` (private recipe + draft revision; ingredients matched via `Ingredient::visibleTo()`, else created as the importer's own). It runs on the queue as `App\Jobs\ImportRecipe`, one `recipe_imports` row per attempt (status, error, tokens); the Recipes list's "Import from link" action and `ImportRecipeStatus` page drive it, hidden without `ANTHROPIC_API_KEY`. New formats (PDF, photo, text) only need a `RecipeSource` constructor — already present — and a way in. The structured-output schema must stay within the API's 16 nullable-field limit (a test guards it), which is why text fields are `""` rather than null.

`RecipeLocaleSlug` keeps a recipe's public URL slug per locale, stable across revisions.

`Ingredient` / `Unit` are catalog entities shared across recipes/locales. Only the catalog admin (`User::CATALOG_ADMIN_EMAIL`, see `isCatalogAdmin()`) edits units. Ingredients have a nullable `owner_user_id`: null = shared (created by the admin, visible to all), otherwise private to its creator (set by a `creating` hook). Use `Ingredient::visibleTo($user)` for listing/picking — it is a local scope, not a global one, so recipe lines still resolve any ingredient they reference. `Ingredient` keeps its `IngredientTranslation` sibling so a single catalog row can be displayed in multiple languages — this is the only translation table left, and it's deliberately scoped to the catalog, not to revision content.

### Households (Jetstream teams)

A household is a Jetstream team under our own names. The models are `App\Models\Household`, `HouseholdInvitation` and `HouseholdMembership`, and the tables are `households`, `household_user` and `household_invitations`, with `households.personal_household`, `users.current_household_id` and `recipes.household_id`. They are registered through `Jetstream::useTeamModel()` and its siblings in `JetstreamServiceProvider`. Jetstream derives most keys from the model class names. The few methods of its own that hardcode `current_team_id` / `personal_team` are overridden: `currentTeam()`, `switchTeam()` and `personalTeam()` on `User`, and `removeUser()` and `purge()` on `Household`. Jetstream's `ValidateTeamDeletion` is not used for the same reason. **Jetstream's method names stay** (`currentTeam`, `ownsTeam`, `allTeams`, `teamInvitations`, the policy's `addTeamMember`, …) because its trait and actions call them. Everything else, including every user-facing word, says "household".

Jetstream is used for its **backend only**: models, `HasTeams`, `HouseholdPolicy` and the actions in `app/Actions/Jetstream`. The household page is a Filament page, `App\Filament\Pages\MyHousehold` (`/app/household`, in the sidebar), which calls those actions to invite, remove, rename, create, switch, leave and delete. **Filament owns authentication**: Fortify's routes are off (`Fortify::ignoreRoutes()`), and Jetstream's own URIs (`/teams/*`, `/user/profile`, `/dashboard`, `/login`) redirect into the panel or 404 (`/team-invitations/*`), see `routes/web.php`. Sanctum, API tokens, 2FA, profile photos and roles are left out. With no roles, every member is equal and only the owner manages membership.

Every user owns one personal household named after their last name (`Household::defaultNameFor()`, translated in `lang/*/household.php`). `App\Actions\Households\CreatePersonalHousehold` creates it and is idempotent. It runs on registration (a listener in `AppServiceProvider` for both Laravel's and Filament's `Registered` events) and ran once for pre-existing users in the `2026_09_25_000050` migration.

**Recipes belong to a household** (`recipes.household_id`) and every member can open and edit every recipe in it. `Recipe::scopeAccessibleTo($user)` is the one access rule: recipes in any of the user's households, plus their own (so leaving keeps what you wrote). Both recipe resources and the fork pickers scope through it; use it for any new recipe query rather than filtering on `owner_user_id`. A new recipe gets the owner's current household from a `creating` hook. **Ingredients** a user owns are shared with everyone they share a household with (`User::householdMemberIds()`, used by `Ingredient::visibleTo()`, `IngredientPolicy` and the unique-name rule). The ingredient picker's "keep the current pick resolvable" exception must come from the saved row, never from submitted state. `HouseholdIsolationTest` pins all of this down.

**Saved recipes** are another household's published recipe kept in this household's collection (`household_saved_recipes`, `Household::savedRecipes()` / `Recipe::savedByHouseholds()`). A bookmark, not a copy: it stays its owners', and the saving household reads whatever they publish next. The public page's "Save to my recipes" button posts to `recipes.share.save` (`SaveSharedRecipe` → `App\Actions\Recipes\SaveRecipeToHousehold`, into the current household); a guest goes through `recipes.share.sign-in` so login lands back on the page. `Recipe::scopeSavedBy()` only counts a save while the recipe is shared and has a published revision, and the saver never sees drafts (`Recipe::publishedRevision()`, `revisionFor()`). Both recipe resources list and open `viewableBy()` (accessible + saved); **`RecipePolicy` / `RecipeRevisionPolicy` are what keep saved recipes read-only**, so `accessibleTo()` / `isEditableBy()` remain the edit rule and bulk deletes must `authorizeIndividualRecords('delete')`. "Make my own version" (`App\Actions\Recipes\ForkRecipeRevision`) creates a private recipe owned by the user in the household that saved it, with `forked_from_*` set and v1 a draft copy of the revision (`RecipeRevision::forkInto()`); the original's private ingredients are swapped for a same-named one the user can see, else a private copy, so the fork never leans on another household's catalog. Once a household has forked a saved recipe, the Recipes list shows only the fork (`Recipe::scopeWithoutForkedSaves()`) and the public page opens the fork. The save row is kept so the fork's "Based on" link still opens the original. `SavedRecipesTest` covers this.

Invitations are sent as `App\Mail\HouseholdInvitationMail` (template `resources/views/mail/household-invitation.blade.php`, linking new people to the panel's registration) and accepted at `/households/invitations/{id}` by `AcceptHouseholdInvitation`: only from the invited address, switching the member into the household, and confirming with a Filament notification.

Do not rerun `php artisan jetstream:install`. It overwrites `User.php`, the factory, the seeder, `vite.config.js` and `app.css`, downgrades Tailwind to v3, strips `dark:` classes from Filament views, brings back the UI removed here, and publishes `team`-named migrations.

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
- **Revisions are only created when asked for.** Opening the editor on a published revision does not fork it silently: `EditRecipeRevision::revisionChoiceAction()` asks first, and the cook picks one of three — start a new revision (a `draftFork()`, or the locale's open draft if one exists), edit this published version in place, or leave it alone and go back. Nothing is written until they answer. So a published revision's content *can* change, which is why `RecipeRevision::UPDATED_AT = null` no longer means what it says; treat a published revision as something people may already be reading rather than as immutable.
- **One revision = one locale**: do not "translate" a revision by adding fields or sibling rows; create a new `RecipeRevision` with a different `locale` value instead.
- **Revision copies must copy everything**: `draftFork()` and `forkInto()` both go through `RecipeRevision::copyInto()`. When you add a revision-scoped table, extend `copyInto()` to clone it, or editing a published revision (or forking a saved one) will silently drop that content.
- **Test DB**: `phpunit.xml` forces `DB_DATABASE=testing`. The MySQL container creates this DB via `docker/mysql/create-testing-database.sh` at first boot — if tests fail with "unknown database 'testing'" on a fresh volume, ensure that script ran (or `sail down -v` to reset).
- **Queue/cache/session** default to `database` driver in `.env` — tables exist via `0001_01_01_*` migrations. Tests override to `sync`/`array` (see `phpunit.xml`), so don't rely on persisted queue state in tests.
- App locale defaults to `en` (see `APP_LOCALE`), but the domain is locale-aware end-to-end — never assume a single locale when querying revision content.
