# mpcl-wordpress

WordPress plugin `mpcl` that mirrors a user's machine collection from mypclist.net
(API v1, `POST https://mypclist.net/api/`) into a local table and renders it through
shortcodes. The API server lives in the separate MyPCList repository
(`src/Controller/ApiV1/`).

## Tracker

Irminsul, workspace `MPC`, project `WPP` (task codes `WPP-123`). Read the whole task,
comments included, before starting; decisions are recorded as comments.

## Git

- Trunk is `main`. Commit locally; **never push, push tags or publish a release
  without the owner's explicit approval.**
- One logical change per commit, prefixed `Resolved WPP-123: ` when it closes a task.
  Short imperative messages, no AI attribution.

## Compatibility

- 2.x: WordPress 6.9+ on PHP 7.4+. Do not use anything newer than PHP 7.4
  (no union types, `mixed`, `match`, named arguments, constructor promotion, enums,
  `readonly`, nullsafe operator).
- 1.x (legacy, Smarty/JBBCode/connector bundled) was declared for WordPress 4.4 on
  PHP 5.6; 1.2.2 is the last 1.x release.
- An upgrade must keep the site connected and all data. These are public contracts:
  - plugin path `mpcl/mpcl.php`, text domain `mpcl`;
  - option `mpcl-options` and its keys (`api_key`, `api_token`, `catalog_page_id`,
    `box_full_width`, `entries_per_page`, `cache_autoupdate_enabled`,
    `cache_autoupdate_interval`, `last_listing_cache`);
  - table `{prefix}mpcl_machines` and its columns;
  - shortcodes `mpcl`, `mypclist`, `mpcl-catalog` with attributes `columns` and `id`;
  - query var `machine_id` (public URLs), settings page slug `mpcl-settings`;
  - CSS class names (themes override them).

## Code conventions

- Yoda comparisons where they make sense (`null === $x`, `0 < $count`).
- Everything typed: typed properties, parameter and return types. What PHP 7.4 cannot
  express goes into docblocks (`@return list<Machine>`).
- Data crosses boundaries as DTOs, not associative arrays.
- No singletons, globals or static state; dependencies go through constructors.
- Escape at output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`); sanitise input.
- English only in code; user-facing strings go through `__('…', 'mpcl')`. Never guess
  a Polish translation: leave it empty and report it.
- No runtime Composer dependencies in the shipped plugin.

## Packaging

`create-package.sh` builds `mpcl-wordpress-<version>.zip` with a top-level `mpcl/`
directory.
