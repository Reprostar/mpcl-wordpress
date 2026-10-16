# Changelog

## 2.0.1

- The plugin is now called "MyPCList for WordPress" in the plugins list. Nothing
  else changes; the plugin directory and settings stay the same.

## 2.0.0 (unreleased)

Requires WordPress 6.9+ and PHP 7.4+. Updating from 1.x keeps the API credentials,
all settings and the synced machines; no reactivation is needed.

- Rewritten without bundled libraries: Smarty, JBBCode and the PHP connector are
  gone, and the package shrank from 483 KB to about 110 KB. Shortcodes, URLs and
  CSS classes are unchanged.
- The collection is refreshed in the background by WP-Cron instead of during
  page views. "Clear cache" and changing the API credentials trigger an
  immediate refresh.
- Collections larger than 50 machines now sync completely (1.x only ever fetched
  the first 50).
- A refresh replaces the catalogue in one step, so visitors never see an empty
  or partial list, and a failing API keeps the previous copy.
- Machine descriptions, names and image URLs are escaped (stored XSS fix).
- The machines table is converted to utf8mb4, so emoji and other 4-byte
  characters are stored correctly. The unused `mpcl_options` table is removed.
- "Entries per page" now limits the catalogue list (default 100). The
  "Enable image cache" option, which never did anything, is removed.
- `[mpcl]` without `columns` no longer crashes on PHP 8 (defaults to 4 columns).
- Front-end assets are loaded only on pages that use the shortcode.
- The settings page requires the `manage_options` capability and sanitises
  its input.
- The API token is no longer shown on the settings page and is stored encrypted
  (libsodium). The key comes from the site's auth salt, or from
  `MPCL_ENCRYPTION_KEY` if defined in wp-config.php. Changing the salts makes the
  stored token unreadable; the settings page then asks for it again. A token
  saved by 1.x is encrypted on the first request after the update. Going back to
  1.x requires entering the token again.
- The catalogue page is chosen from a list of pages instead of typing its ID;
  pages that use the shortcode are marked.
- A "Test connection" button on the settings page checks the API key and token
  (typed or saved) without saving, and reports the collection size or the error.
- An embedded box for a machine that is not synced yet shows a short notice to
  editors instead of nothing; visitors still see nothing.
- Deleting the plugin removes its tables, options (including the API
  credentials) and scheduled event. Deactivating keeps everything.
- Markdown, plain-text and HTML descriptions are shown as mypclist.net renders
  them (new `description_html` API field, filtered through `wp_kses_post`).
  BBCode descriptions keep the plugin's own rendering; servers without the new
  field fall back to it too.
- No PHP deprecation notices on PHP 8.x.

After updating, the `templates_c/` and `cache/` directories that 1.x left in the
WordPress root and in `wp-admin/` can be deleted.

## 1.2.2

Last release of the 1.x line (WordPress 4.4+, PHP 5.6+).

- Fixed synchronisation: the API is called over HTTPS. Plain HTTP is now
  redirected by the server, which broke syncing, and it sent the API key
  unencrypted.
- Settings page reorganised into sections (Authorization, Catalog page,
  Appearance, Data cache).
- Gallery images no longer use `target="_blank"`, which broke JavaScript gallery
  plugins.
- The plugin header now reports the real version.

Known issue: the "Entries per page" setting has no effect in 1.x.
