# CLAUDE.md — Kalenda

WordPress plugin that renders the **Catholic liturgical calendar** on any theme, sourced from the
**LitCal API** (litcal.johnromanodorazio.com). This is a standalone git repo living inside a host
WordPress site; it will be published to GitHub, then WordPress.org.

Status: scaffold + domain/API layer done. Not yet built: REST (`kalenda/v1`), admin settings,
Gutenberg blocks, i18n. Frontend will be **server-rendered blocks + the WordPress Interactivity API**
(not a client React bundle) fed by a cached `kalenda/v1` REST proxy — decided, not yet implemented.

## Architecture — the one decision that governs everything

The plugin is a **thin WordPress layer over the official `liturgical-calendar/components` library**
(used unmodified). Everything talks to **`Kalenda\Contracts\LitCalGateway`**, never to the vendor
library or HTTP directly. This keeps the data source swappable and the code testable.

Data flow (server-to-server; the browser never calls LitCal):
```
block/shortcode/REST  →  LitCalGateway  →  LitCalClient
                                              ├─ ApiClient (vendor: builds URL, POSTs, validates)
                                              │     └─ WpHttpClient (our wp_remote_request → PSR-7)
                                              └─ TransientCache (our caching, NOT the library's)
```

Key point: when we inject a custom HTTP client, the vendor library **ignores its own cache/logger**.
So **all caching is ours**, in the gateway, via WP transients. Do not wire the library's PSR-16 cache.

## Directory layout

- `kalenda.php` — bootstrap only: header, PHP 8.1 / WP 6.5 guards, `vendor/autoload.php` guard, boot on
  `plugins_loaded`. Defines the `Kalenda\KALENDA_*` constants. Rarely edit.
- `src/` — PSR-4 `Kalenda\`. `Contracts/` (interfaces), `Api/` (gateway impl, HTTP adapter, query VO),
  `Cache/`, `Support/` (Options), `Exceptions/`. `Plugin.php` is the container (singleton + a
  `kalenda_services` filter that later phases hook services into).
- `blocks/` — block sources, compiled into `build/` by `@wordpress/scripts` (`--webpack-src-dir=blocks`).
  Does not exist yet.
- `vendor/`, `build/`, `node_modules/` — gitignored; **must ship in the WP.org zip** (see `.distignore`,
  which is an exclude-list; anything not listed ships).

## Key classes

- `Api\LitCalClient` — the gateway. `create(Options)` factory wires `WpHttpClient` + `TransientCache` +
  the `/api/v5` base URL. Owns tiered caching and stdClass→array normalization.
- `Api\WpHttpClient` — implements the vendor's `Http\HttpClientInterface` (not PSR-18) over
  `wp_remote_request`, returns an `nyholm/psr7` `Response`.
- `Api\CalendarQuery` — immutable value object; `create()` / `from_array()` validate; `cache_key()`.
- `Cache\TransientCache` — WP transients; `flush()` deletes by the `kalenda_` key prefix.
- `Support\Options` — typed accessor over the single `kalenda_settings` option; `defaults()` is the
  source of truth for setting keys.

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
- Event fields of note: `grade`/`grade_lcl` (rank), `color`/`color_lcl` (may be an array), `type`
  (`fixed`|`mobile`), `liturgical_season`, `is_vigil_mass`. Locale controls `*_lcl` fields; each
  calendar supports specific locales (see metadata).

## Conventions that differ from plain PSR

Code follows **WordPress Coding Standards** (see `phpcs.xml.dist`), which override PSR habits:
- **snake_case everywhere** — including method names and even public value-object properties
  (`CalendarQuery::$calendar_id`, `$year_type`). Don't reintroduce camelCase on our symbols even though
  the vendor API uses it (`->yearType()`).
- **Long array syntax** `array()`, **Yoda conditions** (`null === $x`), tabs for indent.
- Every class/method needs a docblock with a short description + `@param`/`@return` (typed properties
  still need `@var`).
- **PSR-4 filenames are StudlyCase** (`Plugin.php`), which conflicts with WPCS `class-*.php`. `src/` is
  deliberately exempted from `WordPress.Files.FileName.*` in `phpcs.xml.dist` — keep new class files in
  `src/` and this stays clean.
- Keep `Contracts/` and value objects **WordPress-agnostic** (no WP functions). WP calls belong only in
  adapters: `WpHttpClient`, `TransientCache`, `Options`, `LitCalClient::create()`, `Plugin`.

## Sanctioned suppressions (there are only these — keep the count minimal)

- `@phpstan-ignore instanceof.alwaysTrue` in `Plugin.php` — the `kalenda_services` filter is untrusted
  input; the runtime guard is real even though the docblock says otherwise.
- `phpcs:ignore/disable WordPress.DB.DirectDatabaseQuery` in `uninstall.php` + `TransientCache::flush()`
  — no core API bulk-deletes transients by prefix; queries are prepared + `esc_like`'d.
- `WordPress.Security.EscapeOutput.ExceptionNotEscaped` excluded for `/src/*` in `phpcs.xml.dist` —
  gateway exceptions are internal diagnostics, caught and converted to `WP_Error` at the REST boundary,
  never echoed. Do **not** add `esc_html()` to domain-layer exception messages.

## Commands

```bash
composer install                 # deps + autoloader
composer run phpcs               # WPCS — must be clean
composer run phpstan             # level 6 (needs --memory-limit=512M, already in the script) — must be clean
composer run test                # PHPUnit (Brain Monkey mocks WP; no live WP needed)
npm run build | start            # compile blocks
npm run env:start                # local WP via wp-env (needs Docker)
```
Both `phpcs` and `phpstan` are the merge gate — run both after any PHP change. Run `vendor/bin/phpcbf`
first to auto-fix formatting.

## Testing strategy

No wp-cli in this environment; `wp-env` needs Docker. Unit tests use **Brain Monkey** (mock WP
functions) + Yoast polyfills — test the gateway/VO/cache with a fake `HttpClientInterface` + `Cache`,
not the network. For end-to-end confidence against the real API, a throwaway shim harness works well
(shim the handful of WP funcs `WpHttpClient`/`TransientCache`/`LitCalClient` use, then drive
`LitCalClient` with real cURL) — that is how Phase 2 was verified. No PHPUnit tests are written yet.

## Pitfalls

- The vendor `ApiClient` is a **process-wide singleton** (`getInstance()` — first init wins). We init it
  once with our HTTP client + base URL. If another plugin bundling the same lib inits first, its config
  wins; the eventual fix is namespace-scoping vendor with php-scoper at build time. Don't try to
  re-init with different config at runtime.
- **Transient keys must start with `kalenda_`** or `uninstall.php` and `TransientCache::flush()` won't
  find them. `CalendarQuery::cache_key()` returns `kalenda_cal_*`; metadata uses `kalenda_metadata`.
- **API base URL is pinned to `/api/v5`** (`v3`/`v4` currently 404; `dev`/`v1`/`v2`/`v5` = 200).
  Override with the `kalenda_api_url` filter, not by editing the constant.
- Never edit anything under `vendor/`. Regenerate `composer.lock` via composer, don't hand-edit.
- `home_url()` is used to build the outbound User-Agent — harmless, but means `WpHttpClient` needs WP
  loaded (that's fine; it's an adapter).

## Backward compatibility

Pre-1.0 (`0.1.0`), nothing is stable yet. Once REST/blocks ship: the `kalenda/v1` REST shape, block
attributes, `kalenda_*` hooks, and the `kalenda_settings` option shape become the public contract —
add, don't break. `Options::defaults()` keys are that contract for settings.
