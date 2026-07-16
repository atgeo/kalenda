=== Kalenda ===
Contributors: georgeskmeid
Tags: catholic, liturgical calendar, liturgy, calendar, litcal
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display the Catholic liturgical calendar on WordPress themes using a native block, powered by the LitCal API.

== Description ==

Kalenda brings the Catholic liturgical calendar to WordPress. It uses the open LitCal API to show liturgical celebrations, their rank and liturgical colour — for the General Roman Calendar as well as national and diocesan calendars, in the locales each supports.

Kalenda is built for modern WordPress:

* A Kalenda Day block — shows today's liturgical celebration(s), server-rendered for compatibility and fast page output so it works with any theme. (A month/grid calendar block is not available yet.)
* A cached REST API (`kalenda/v1`) so your own themes and plugins can read liturgical data without calling the upstream service directly.
* Clean, standards-based code — PSR-4 autoloading, the WordPress Coding Standards, and static analysis.

Liturgical data is provided by the [LitCal project](https://litcal.johnromanodorazio.com/) by John Romano D'Orazio.

== Installation ==

1. Upload the `kalenda` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Add the **Kalenda Day** block to any post or page from the block inserter, then set the calendar, language, and heading from the block's settings panel in the editor. There is no separate admin settings screen yet.

== External Services ==

Kalenda connects to the public LitCal API to retrieve Catholic liturgical calendar data.

When data is requested, the plugin sends:
* The requested liturgical year.
* The requested year type.
* The requested locale.
* The requested calendar identifier, when supported by the plugin configuration.

No personal information, user accounts, or site content is transmitted.

Requests are made only when liturgical data is required. Responses are cached locally in WordPress to reduce external requests.

LitCal API:
https://litcal.johnromanodorazio.com/

LitCal API documentation:
https://github.com/Liturgical-Calendar/LiturgicalCalendarAPI

== Frequently Asked Questions ==

= Does this require an account or API key? =

No. Kalenda talks to the public LitCal API. Responses are cached in WordPress so pages stay fast and upstream requests are minimised.

= Which calendars are supported? =

The General Roman Calendar is supported.

Additional national and diocesan calendars from the LitCal project may be supported in future versions.

= Does it work with my theme? =

Yes. The block is server-rendered with plain, theme-agnostic markup, so it works in block and classic themes alike. Liturgical colours currently ship as fixed CSS classes rather than theme-overridable custom properties.

== Changelog ==

= 0.1.0 =
* REST API (`kalenda/v1`): `/calendar`, `/day` and `/calendars`, with metadata-based validation and cached, rate-limit-friendly responses.
* Kalenda Day block: shows today's celebration(s), server-rendered.
