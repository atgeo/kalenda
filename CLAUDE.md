# CLAUDE.md — Kalenda

WordPress plugin that renders the **Catholic liturgical calendar** on any theme, sourced from the
**LitCal API** (litcal.johnromanodorazio.com). This is a standalone git repo living inside a host
WordPress site; it will be published to GitHub, then WordPress.org.

**Status:** REST API, the `kalenda/day` block, and a French translation have shipped. **Not built
yet:** an admin settings screen, a month/grid calendar block, and any front-end JS interactivity
(`@wordpress/interactivity` is a declared dependency but is not imported or used anywhere). A test
suite exists at `tests/phpunit/` — keep it passing; see Testing strategy below for a time it broke
silently.

## Architecture — the one decision that governs everything

The plugin is a **thin WordPress layer over the official `liturgical-calendar/components` library**
(used unmodified). Everything talks to **`Kalenda\Contracts\LitCalGateway`**, never to the vendor
library or HTTP directly. This keeps the data source swappable and the code testable.

Data flow (server-to-server; the browser never calls LitCal):
```
REST controller ─┐
                 ├─→ CalendarRepository ─→ LitCalGateway ─→ LitCalClient
render.php ──────┘        │                                    ├─ ApiClient (vendor: builds URL, POSTs, validates)
   (via kalenda() helper) │                                    │     └─ WpHttpClient (our wp_remote_request → PSR-7)
                          └─→ MetadataAllowlist                └─ TransientCache (our caching, NOT the library's)
```
`CalendarRepository` and `DayService` are the shared middle layer: both the REST layer
(`CalendarController`) and the block template (`blocks/day/render.php`, via the `kalenda()` helper)
go through them, so allowlist validation, 502-mapping and day-filtering exist exactly once.

Key point: when we inject a custom HTTP client, the vendor library **ignores its own cache/logger**.
So **all caching is ours**, in the gateway, via WP transients. Do not wire the library's PSR-16 cache.

## Directory layout

- `kalenda.php` — bootstrap: header, PHP 8.1 / WP 6.5 guards, `vendor/autoload.php` guard, boot on
  `plugins_loaded`. Defines the `Kalenda\KALENDA_*` constants and the global `Kalenda\kalenda()`
  helper (`kalenda(): Plugin` — returns `Plugin::instance()`). Rarely edit.
- `src/` — PSR-4 `Kalenda\`:
  - `Contracts/` — interfaces (`LitCalGateway`, `Cache`, `Registrable`, `RouteProvider`).
  - `Api/` — gateway impl (`LitCalClient`), HTTP adapter (`WpHttpClient`), query VO (`CalendarQuery`).
  - `Cache/` — `TransientCache`.
  - `Repositories/` — `CalendarRepository`: allowlist check + gateway fetch + 502 mapping, shared by REST and blocks.
  - `Services/` — `DayService`: filters a year's `litcal` array down to one day, shared the same way.
  - `Rest/` — `RestRegistrar` (route registrar), `CalendarController` (`/calendar`, `/day`),
    `MetadataController` (`/calendars`), `MetadataAllowlist` (calendar-id/locale validation against
    live metadata — lives under `Rest/` but is calendar-domain logic, not REST-specific; also
    consumed by `Repositories\CalendarRepository`).
  - `Blocks/` — `BlockRegistrar`: registers block types from `build/<name>` and the "Kalenda" block category.
  - `Support/` — `Options`.
  - `Exceptions/` — `GatewayException`.
  - `Plugin.php` — the container: singleton, memoizes `calendar_repository()`/`day_service()`, wires
    the `kalenda_services` filter.
- `blocks/day/` — the one shipped block (`kalenda/day`): `block.json` (apiVersion 3, no
  `viewScript`), `edit.js` (Inspector controls + `ServerSideRender` preview), `save.js` (returns
  `null` — fully dynamic block), `render.php` (server template, reads `kalenda()->calendar_repository()`
  and `kalenda()->day_service()` directly since templates aren't constructor-injected), `style.scss`.
  Compiled into `build/day/` by `@wordpress/scripts`.
- `assets/` — plugin icon files (png/svg). Not yet tracked by `.distignore` either way — check before
  assuming they're excluded from the WP.org zip.
- `languages/` — `kalenda.pot` plus a checked-in French translation (`kalenda-fr_FR.po`/`.mo`). There
  is also a stray `kalenda-fr_FR.po~` editor backup file sitting in the repo — harmless, but don't
  mistake it for a real translation file.
- `tests/phpunit/` — PHPUnit + Brain Monkey. See Testing strategy.
- `vendor/`, `build/`, `node_modules/` — gitignored; `vendor/` and `build/` **must ship in the WP.org
  zip** (see `.distignore`, which is an exclude-list; anything not listed ships).

## REST endpoints (`kalenda/v1`) — the current public contract

All public, read-only, `permission_callback => '__return_true'`, backed by `CalendarRepository`'s
transient cache plus a per-response `Cache-Control` header.

- `GET /calendar` — params `type` (`general`|`nation`|`diocese`), `calendar_id`, `year` (1970–9999),
  `year_type` (`LITURGICAL`|`CIVIL`), `locale`. Defaults come from `Options`.
- `GET /day` — same `type`/`calendar_id`/`locale`, plus `date` (`Y-m-d`, optional — defaults to today
  in `wp_timezone()`). No `year`/`year_type` (derived from `date`, always `CIVIL`). `date` is
  validated with `checkdate()` in the arg schema's `validate_callback`, not inside the route handler —
  test it by capturing the registered args, not by calling `get_day()` directly (see Testing strategy).
- `GET /calendars` — LitCal metadata (available nations/dioceses/locales). No params.

## Key classes

- `Repositories\CalendarRepository::fetch(CalendarQuery): array|WP_Error` — validate against the live
  metadata allowlist (`MetadataAllowlist`), then `gateway->calendar()`; catches `GatewayException` and
  returns a `kalenda_upstream_unavailable` `WP_Error` (502) instead, without leaking the exception's
  own message.
- `Services\DayService::filter(array $events, DateTimeImmutable $date): array` — pure, no
  dependencies; returns every event whose `date` starts with the given day (a vigil Mass and the next
  day's feast can share a date).
- `Rest\MetadataAllowlist` — matches locale requests on the primary language subtag (`en` ⇄ `en_US`),
  not exact string equality — general calendars advertise language codes, national/diocesan calendars
  advertise full locales, and LitCal itself falls back within a language.
- `Api\LitCalClient` — the gateway. `create(Options)` factory wires `WpHttpClient` + `TransientCache` +
  the `/api/v5` base URL. Owns tiered caching and stdClass→array normalization.
- `Api\WpHttpClient` — implements the vendor's `Http\HttpClientInterface` (not PSR-18) over
  `wp_remote_request`, returns an `nyholm/psr7` `Response`.
- `Api\CalendarQuery` — immutable value object; `create()` / `from_array()` validate; `cache_key()`.
- `Cache\TransientCache` — WP transients; `flush()` deletes by the `kalenda_` key prefix.
- `Support\Options` — typed accessor over the single `kalenda_settings` option; `defaults()` is the
  source of truth for setting keys.
- `Plugin` — `calendar_repository()` and `day_service()` are memoized per-request; `gateway()` is
  **not** (see Pitfalls).

## Domain rules (LitCal specifics — get these wrong and results are silently wrong)

- **Three calendar types**: `general` (General Roman), `nation` (e.g. `US`, `IT`), `diocese` (e.g.
  `romamo_it`). `calendar_id` is required for nation/diocese, absent for general. Valid ids come from
  the live `/calendars` metadata — validate against it (the allowlist), don't hardcode.
- **`year_type`**: `LITURGICAL` (Advent→Advent) vs `CIVIL` (Jan→Dec). Default `LITURGICAL`.
- **Year range 1970–9999.** Past years are **liturgically immutable** and historically accurate (1985
  returns the 1985 calendar, not today's) → cached for `YEAR_IN_SECONDS`. Current/future years can
  change via decrees → cached for `Options::cache_ttl()`. This tiering lives in `LitCalClient::ttl_for_year()`.
- The calendar endpoint is fetched via **POST** (the vendor library does this); `/calendars` is GET.
- Gateway responses are normalized arrays with keys `litcal`, `settings`, `metadata`, `messages`.
- Event fields of note: `grade`/`grade_lcl` (rank), `color`/`color_lcl` (may be an array — see the
  Pitfalls entry on `render.php`), `type` (`fixed`|`mobile`), `liturgical_season`, `is_vigil_mass`.
  Locale controls `*_lcl` fields; each calendar supports specific locales (see metadata).

## Conventions that differ from plain PSR

Code follows **WordPress Coding Standards** (see `phpcs.xml.dist`), which override PSR habits:
- **snake_case everywhere in PHP** — including method names and even public value-object properties
  (`CalendarQuery::$calendar_id`, `$year_type`). Don't reintroduce camelCase on our symbols even though
  the vendor API uses it (`->yearType()`).
- **`block.json` attributes and JS are camelCase** (`calendarId`, `showDate`) — that's standard
  Gutenberg convention, not a violation of the snake_case rule above; the rule only applies to PHP.
- **Long array syntax** `array()`, **Yoda conditions** (`null === $x`), tabs for indent.
- Every class/method needs a docblock with a short description + `@param`/`@return` (typed properties
  still need `@var`).
- **PSR-4 filenames are StudlyCase** (`Plugin.php`), which conflicts with WPCS `class-*.php`. `src/` is
  deliberately exempted from `WordPress.Files.FileName.*` in `phpcs.xml.dist` — keep new class files in
  `src/` and this stays clean.
- Keep `Contracts/` and value objects **WordPress-agnostic** (no WP functions). WP calls belong only in
  adapters: `WpHttpClient`, `TransientCache`, `Options`, `LitCalClient::create()`, `Plugin`,
  `Repositories/`, `Rest/`, `Blocks/`.
- `BlockRegistrar::register_blocks()` calls `constant( 'Kalenda\\KALENDA_PATH' )` instead of referencing
  `KALENDA_PATH` directly — a static-analysis workaround. Don't "simplify" this back to a bare
  constant reference without checking PHPStan still passes.

## Sanctioned suppressions (keep the count minimal, and each one itemized)

- `@phpstan-ignore instanceof.alwaysTrue` in `Plugin.php` — the `kalenda_services` filter is untrusted
  input; the runtime guard is real even though the docblock says otherwise.
- `phpcs:ignore/disable WordPress.DB.DirectDatabaseQuery` in `uninstall.php` + `TransientCache::flush()`
  — no core API bulk-deletes transients by prefix; queries are prepared + `esc_like`'d.
- `WordPress.Security.EscapeOutput.ExceptionNotEscaped` excluded for `/src/*` in `phpcs.xml.dist` —
  gateway exceptions are internal diagnostics, caught and converted to `WP_Error` at the REST boundary,
  never echoed. Do **not** add `esc_html()` to domain-layer exception messages.
- `// phpcs:ignoreFile` at the top of `blocks/day/render.php` — a **whole-file** suppression, broader
  than the itemized policy above. It is currently masking a real bug (see Pitfalls) — if you touch
  this file, replace the blanket ignore with targeted ones rather than leaving it as-is.

## Commands

```bash
composer install                 # deps + autoloader
composer run phpcs               # WPCS — must be clean
composer run phpstan             # level 6 (needs --memory-limit=512M, already in the script) — must be clean
composer run test                # PHPUnit (Brain Monkey mocks WP; no live WP needed)
npm run build | start             # compile blocks
npm run env:start                # local WP via wp-env (needs Docker)
```
`phpcs`, `phpstan` and `test` are all merge gates — run all three after any PHP change. Run
`vendor/bin/phpcbf` first to auto-fix formatting. Note `phpstan.neon.dist` only scans `src/` — it
will not catch problems in `blocks/*/render.php` (see Pitfalls).

## Testing strategy

Brain Monkey (mocks WP functions) + Yoast polyfills + hand-written class doubles for `WP_Error` /
`WP_REST_Request` / `WP_REST_Response` / `WP_REST_Server` in `tests/phpunit/wp-stubs.php` (Brain
Monkey only fakes functions, not classes; there's no real WordPress loaded for these tests). Base
`TestCase` wires `Brain\Monkey\setUp()`/`tearDown()` into the Yoast polyfill's `set_up()`/`tear_down()`
hooks. `Fakes/FakeLitCalGateway` is a configurable `LitCalGateway` double (fixed data or a thrown
exception, plus a call counter).

`tests/phpunit/Rest/CalendarControllerTest.php` covers the three highest-value REST behaviours:
unknown `calendar_id` → 400 (and the gateway is never reached), an impossible `/day` date
(`2026-02-30`) → 400, and `GatewayException` → 502 without leaking its message. **The `/day` date test
captures the route's registered args via a mocked `register_rest_route()` and calls the
`validate_callback` directly** — that check lives in the arg schema, not in `get_day()`, so calling
`get_day()` directly with a bad date would not catch it (the date silently rolls over instead).

**Known trap, already hit once:** when `CalendarRepository`/`DayService` were extracted out of
`CalendarController`, the controller's constructor signature changed (`LitCalGateway, Options` →
`CalendarRepository, Options, DayService`) but the test file wasn't updated, and stayed broken
(`TypeError`) until caught by actually running `composer run test`. **Whenever you change a
constructor's dependencies, grep `tests/` for that class and update every call site — don't assume
green CI/lint means the tests still run.** PHPCS and PHPStan both stayed clean the whole time this was
broken; only `phpunit` catches it.

## Pitfalls

- The vendor `ApiClient` is a **process-wide singleton** (`getInstance()` — first init wins). We init it
  once with our HTTP client + base URL. If another plugin bundling the same lib inits first, its config
  wins; the eventual fix is namespace-scoping vendor with php-scoper at build time. Don't try to
  re-init with different config at runtime.
- **`Plugin::gateway()` builds a brand-new `LitCalClient` on every call** (unlike
  `calendar_repository()`/`day_service()`, which are memoized). It's currently only used by
  `MetadataController`, so the cost is one extra `Options::load()` + client construction per metadata
  request — not free, and inconsistent with the memoized pattern next to it. If you add another caller,
  memoize it the same way `calendar_repository()` is.
- **`blocks/day/render.php` line ~67 has a live bug, currently hidden by the file's blanket
  `phpcs:ignoreFile`:** `esc_attr( (string) $event['color'][0] ) ?? 'white'` — `esc_attr()` always
  returns a string, so the `??` fallback is dead code; it guards the *escaped output*, not the raw
  `$event['color'][0]` access. If an event has no `color`, this warns/errors instead of falling back.
  Fix by checking `$event['color'][0] ?? 'white'` first, then escaping the result.
- **`block.json`'s `date` attribute is unused.** It's declared (default `""`) but `edit.js` has no
  control for it and `render.php` never reads `$attributes['date']` — the block always renders
  "today" via `current_datetime()`. Don't assume setting it does anything until this is wired up.
- **Transient keys must start with `kalenda_`** or `uninstall.php` and `TransientCache::flush()` won't
  find them. `CalendarQuery::cache_key()` returns `kalenda_cal_*`; metadata uses `kalenda_metadata`.
- **API base URL is pinned to `/api/v5`** (`v3`/`v4` currently 404; `dev`/`v1`/`v2`/`v5` = 200).
  Override with the `kalenda_api_url` filter, not by editing the constant.
- Never edit anything under `vendor/`. Regenerate `composer.lock` via composer, don't hand-edit.
- `home_url()` is used to build the outbound User-Agent — harmless, but means `WpHttpClient` needs WP
  loaded (that's fine; it's an adapter).

## Backward compatibility

Pre-1.0 (`0.1.0`), nothing is contractually stable yet, but the following are **live and in real
use** — treat changes to them as additive, not breaking, even pre-1.0: the `kalenda/v1` REST shape
(`/calendar`, `/day`, `/calendars`), the `kalenda/day` block's attributes, `kalenda_*` hooks
(`kalenda_services`, `kalenda_api_url`), and the `kalenda_settings` option shape
(`Options::defaults()` keys are that contract).
