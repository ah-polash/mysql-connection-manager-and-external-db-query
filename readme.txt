=== DB Connection Manager ===
Contributors: openai-assistant
Tags: database, mysql, mongodb, shortcode, api, integration
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage external database connections (MySQL and MongoDB) from WordPress and run read-only queries from posts and pages with a shortcode.

== Description ==
DB Connection Manager lets administrators register external MySQL or MongoDB instances as a dedicated "DB Connection" custom post type. Each connection stores the server credentials, optional JSON options, and the latest test status so editors can safely reuse approved credentials across the site.

Key highlights include:

* Inline "Test Connection" button with AJAX feedback and automatic JSON validation for additional options.
* Status badges in the list table and editor that display the latest connection message and timestamp.
* Admin columns that surface the connection host, database, username, and any JSON options for quick auditing.
* Front-end shortcode that limits execution to read-only queries, automatically applies `LIMIT` clauses to MySQL statements, and renders tabular or JSON output.

The plugin is ideal for reporting dashboards, lightweight data portals, or any content that needs to expose data from external databases without granting direct database credentials to authors.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/db-connection-manager/` directory or install the plugin through the WordPress admin by uploading the ZIP archive.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **DB Connections → Add New** to create your first connection, fill in the credentials, and click **Test Connection** to verify access.
4. Publish the connection once it reports a successful status.
5. Insert the `[external_db_query]` shortcode into any post or page to render data from a saved connection.

== Frequently Asked Questions ==

= Which database engines are supported? =
Currently the plugin supports MySQL (using the `mysqli` extension) and MongoDB (using the `mongodb` PHP extension). Both extensions must be available on the server for their respective connection types.

= Are the credentials secure? =
Connection credentials are stored as post meta and are only visible to users with the capability to manage DB connections (editors and administrators by default). Password fields are masked in the admin UI, and AJAX requests are nonce protected.

= What queries can I run with the shortcode? =
For MySQL connections, only `SELECT` statements are allowed and the plugin automatically adds a `LIMIT` clause if one is not supplied. MongoDB connections accept JSON-encoded filters, projections, and an optional limit for the number of returned documents. All results are read-only.

= How do I display the query results? =
Use the `template` attribute to switch between an HTML table (`table`, default) and formatted JSON (`json`) when querying MySQL. MongoDB responses are always rendered as formatted JSON for clarity.

== Shortcode Reference ==

```
[external_db_query id="123" query="SELECT * FROM wp_users" limit="25" template="table"]
```

* `id` (required): The post ID of a published DB Connection.
* `query` (MySQL only): SQL `SELECT` query to execute. Any other statement type is blocked.
* `limit` (MySQL only): Maximum number of rows to fetch when no `LIMIT` clause is included.
* `template` (MySQL only): Choose `table` (default) for an HTML table or `json` for a JSON code block.

MongoDB example:

```
[external_db_query id="124" collection="users" filter='{"status":"active"}' projection='{"email":1,"_id":0}' limit="10"]
```

* `collection`: MongoDB collection name to query.
* `filter`: JSON encoded query filter. Invalid JSON produces an error message instead of running the query.
* `projection`: JSON encoded field projection (optional).
* `limit`: Maximum number of documents to return (default 20).

== Screenshots ==

1. Connection editor showing credential fields, options JSON, and live status banner.
2. Admin list table summarizing connection details and latest test results.
3. Example front-end rendering of the shortcode in table mode.

== Changelog ==

= 1.1.0 =
* Added persistent status summary within the connection editor.
* Improved AJAX testing to return timestamps and refresh status indicators in real time.
* Updated admin assets and readme to reflect the enhanced feedback experience.

= 1.0.0 =
* Initial release with DB Connection custom post type, connection testing, and front-end shortcode.

== Upgrade Notice ==

= 1.1.0 =
The editor now shows real-time status updates after testing a connection. Update to surface accurate connection health information without reloading the page.
