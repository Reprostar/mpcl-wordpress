# MyPCList for WordPress

Shows your [MyPCList](https://mypclist.net) machine collection on your WordPress site: a catalogue page
with a view of every machine, and boxes that embed a single machine in any post or page.

## Requirements

- WordPress 6.9 or newer, PHP 7.4 or newer.
- An API key and token from [mypclist.net/settings/api](https://mypclist.net/settings/api).

Version 1.2.2 is the last release for older sites (declared for WordPress 4.4 on PHP 5.6).

## Installation

Download `mpcl-wordpress-<version>.zip` from the
[releases](https://github.com/Reprostar/mpcl-wordpress/releases) and upload it in
Plugins → Add New → Upload Plugin, or extract it into `wp-content/plugins/`. The package has no external
dependencies.

To work on the plugin, clone this repository into `wp-content/plugins/mpcl`.

## Setup

In Settings → MyPCList Integration:

1. Enter the API key and token, then use **Test connection** to check them.
2. Choose the page that shows your catalogue.

The collection is copied to your site and refreshed in the background by WP-Cron (every hour by default,
configurable in the same settings). Sites with `DISABLE_WP_CRON` need a system cron calling `wp-cron.php`.

The API token is stored encrypted with a key derived from the site's security salts. To use a dedicated
key instead, define `MPCL_ENCRYPTION_KEY` in `wp-config.php` before entering the token. This protects the
token in database dumps and backups, not on a compromised server.

## Usage

Put the catalogue on a page:

```
[mpcl columns=4]
```

`columns` sets the number of grid columns (default 4). The view of a single machine opens on the same page.

Embed one machine in any post or page:

```
[mpcl id=15]
```

where `15` is the machine's ID on MyPCList.

## Bundled code

- [baguetteBox.js](https://feimosi.github.io/baguetteBox.js/) lightbox
- a BBCode converter adapted from [JBBCode](https://github.com/jbowens/jBBCode) (MIT, see `licenses/jbbcode.txt`)

## License

MIT, see `LICENSE`. Changes are listed in `CHANGELOG.md`.
