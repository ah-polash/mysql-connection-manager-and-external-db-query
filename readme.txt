# DB Connection Manager

WordPress plugin that registers a **DB Connection** custom post type so administrators can store, test and reuse external database credentials (MySQL and MongoDB).

## Features
- Custom post type "DB Connection" with meta fields for host, port, username, password, database/auth source and additional JSON options.
- Inline "Test Connection" button to validate credentials using AJAX, with feedback for JSON mistakes.
- Connection status, metadata and options displayed in the admin list table for quick review.
- Automatic LIMIT injection for MySQL queries (unless you provide one) to help avoid runaway result sets.
- Shortcode `[external_db_query]` to run read-only queries against the external database and render the result on the front-end.

## Shortcode Usage
```
[external_db_query id="123" query="SELECT * FROM wp_users" limit="25" template="table"]
```
- `id` (required): ID of the DB Connection post. Only published connections are accessible to the shortcode.
- `query` (MySQL only): SQL query. Only `SELECT` statements are allowed. A `LIMIT` clause will be applied automatically unless one already exists.
- `limit` (MySQL only): Caps the number of returned rows when a LIMIT clause is not present.
- `template` (MySQL only): `table` (default) renders an HTML table, `json` renders a JSON code block.

MongoDB example:
```
[external_db_query id="124" collection="users" filter='{"status":"active"}' projection='{"email":1,"_id":0}' limit="10"]
```
- `collection`: MongoDB collection name.
- `filter`: JSON encoded query filter. Invalid JSON will display an error instead of running the query.
- `projection`: JSON encoded field projection (optional).
- `limit`: Maximum number of documents (default 20).

## Testing
Run a basic syntax check:
```
php -l db-connection-manager.php
```
