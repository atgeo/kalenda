# Kalenda

A modern WordPress plugin that displays the **Catholic liturgical calendar** on any theme, powered by the [LitCal API](https://litcal.johnromanodorazio.com/).

> ⚠️ **Status:** early development (`0.1.0`). The public API and blocks are not yet stable.

## Features (planned)

- 📅 **Liturgical calendar block** — month grid or agenda/list view for any liturgical year.
- 🕯️ **Liturgical day block** — today's celebration, rank, liturgical colour and season.
- 🌍 **Any calendar** — General Roman, national and diocesan calendars, in multiple locales.
- ⚡ **Fast & theme-agnostic** — server-rendered with the WordPress Interactivity API, cached via transients.
- 🔌 **REST API** — a cached `kalenda/v1` namespace your own code can consume.

## Architecture

Kalenda is a thin, well-isolated WordPress layer over the official
[`liturgical-calendar/components`](https://packagist.org/packages/liturgical-calendar/components) library.

- **`Kalenda\Contracts\LitCalGateway`** — the only interface the plugin talks to for calendar data.
- **`Kalenda\Api\LitCalClient`** — implements the gateway over the components library's `ApiClient`.
- WordPress adapters supply the library's PSR-18 HTTP client (the WP HTTP API) and PSR-16 cache (transients).
- **`kalenda/v1` REST endpoints** proxy and cache LitCal; blocks and third parties call these, never LitCal directly.
- **Blocks** are registered from `block.json`, server-rendered in `render.php`, and made interactive on the front end with the WordPress Interactivity API.

```
kalenda.php          Bootstrap: guards, autoload, boot
src/                 PSR-4 (Kalenda\) — gateway, REST, admin, blocks glue
blocks/              Block sources (built into build/ by @wordpress/scripts)
build/               Compiled block assets
languages/           Translations
tests/               PHPUnit tests
```

## Requirements

- WordPress **6.5+**
- PHP **8.1+**

## Development

```bash
composer install        # PHP dependencies + autoloader
npm install             # block toolchain
npm run build           # compile blocks into build/
npm run start           # watch mode

composer run phpcs      # WordPress Coding Standards
composer run phpstan    # static analysis
composer run test       # PHPUnit

npm run env:start       # local WordPress via wp-env (requires Docker)
```

## License

[GPL-2.0-or-later](LICENSE). Liturgical data is provided by the LitCal project.
