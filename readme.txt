=== WP Tables ===
Contributors: ianthompson
Tags: csv, tables, shortcode
Requires at least: 6.0
Stable tag: 0.1.0
License: GPLv2 or later

Create frontend tables from CSV files in the WordPress media library.

== Usage ==

1. Activate WP Tables.
2. Go to Tables > Add New and give the table a title.
3. Select or upload a CSV file from the media library.
4. Choose whether the first CSV line is the column heading and whether the table should use the responsive wrapper.
5. Save or publish the table.
6. Copy the generated `[wptables id="123"]` shortcode into a post, page, or shortcode block.

== GitHub Updates ==

WP Tables checks public GitHub releases for update packages. To publish an update, bump the plugin version and stable tag, commit the change, then push a version tag such as `v0.2.0`.

The release workflow attaches a `wp-tables.zip` package to that GitHub release. WordPress will offer the release as an update when its tag version is newer than the installed plugin version.
