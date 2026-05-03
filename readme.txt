=== Talaxie Core ===
Contributors: talaxiecommunity
Tags: mcp, ai, claude, cpt, custom-post-type, taxonomy, etl, sudo, abilities-api
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for the Talaxie community website. Custom post types, taxonomies, and a sudo-style MCP surface for AI agents.

== Description ==

Talaxie Core is the companion plugin to the Talaxie WordPress theme. It carries the data layer of the community website (custom post types for releases and contributors, a taxonomy for Talaxie components) and exposes the site to Model Context Protocol (MCP) clients with a Unix sudo-inspired access model so AI agents can manage content safely.

= What is Talaxie? =

Talaxie is the open source community fork of Talend Open Studio (ETL), born after Qlik discontinued the free version of Talend.

= Custom post types =

* `talaxie_release` — public Talaxie releases (binaries, changelog, milestones). Specialized capabilities (`edit_talaxie_releases`, `delete_talaxie_releases`, …) granted to admins, editors, and the dedicated `ai_bot` role.
* `talaxie_contributor` — community members (GitHub login, role, organization).

= Taxonomies =

* `talaxie_component` — Talaxie components attached to releases (tCore, tDBInput, tFileInputDelimited, …).

= MCP surface (sudo-style) =

The plugin exposes 30 abilities to MCP clients (Claude Code, etc.) through the WordPress 6.9 Abilities API and the [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) plugin:

* **Site** — get-info, get-option (read), update-option (test-only).
* **Posts / Pages** — list, get, create, update; delete is sudo-gated and test-only.
* **Media** — list, upload (base64 payload, 16 MiB cap), delete (test-only).
* **Releases** — full CRUD; the `ai_bot` role owns these without sudo.
* **Users** — list, get; create/update test-only and sudo-gated.
* **Plugins** — list; activate test-only and sudo-gated.
* **Network (multisite)** — create-site, delete-site (test-only, super_admin).
* **Generic** — `wp/v2/*` REST proxy as a test-only escape hatch.
* **Audit** — paginated read of every ability invocation (allow/deny, capability, sudo usage).

Two MCP servers are registered:

1. `talaxie-mcp-prod-server` — production-safe abilities only. Always exposed.
2. `talaxie-mcp-test-server` — every ability. Exposed only when `WP_ENVIRONMENT_TYPE` is `local` or `development`.

= Sudo tokens =

The `ai_bot` role gets narrow permissions (read, edit posts/pages, upload files, full CRUD on Talaxie CPTs). Anything beyond that — site options, plugin activation, user management, deletion of regular posts — requires a sudo token issued by a human admin.

Tokens are short-lived (default 15 min, max 60 min), scoped to a list of capabilities, and stored as bcrypt hashes in a custom table — so a database dump alone cannot replay an elevation. Generate them via:

* **wp-admin** — *Tools > MCP Sudo* (form + nonce, displayed once)
* **WP-CLI** — `wp talaxie mcp sudo-token --scope=manage_options [--ttl=15m] [--single-use]`
* **REST** — `POST /wp-json/talaxie-core/v1/mcp/sudo-token` (caller must hold `manage_options`)

= Audit trail =

Every capability check is recorded in the non-public `talaxie_mcp_audit` post type (ability, capability, allowed/denied, sudo used, IP). A daily cron purges entries older than 30 days (filterable via `talaxie_mcp_audit_retention_days`).

= Drift detection =

`GET /wp-json/talaxie-core/v1/mcp/abilities-on-server` (cap: `manage_options`) reports the tools each MCP server actually exposes and flags any ability with `is_allowed_on_production() = false` that leaked onto the production server.

== Installation ==

1. Upload the plugin folder `talaxie-core` to `/wp-content/plugins/`.
2. Run `composer install` inside the plugin folder (installs the PSR-4 autoloader).
3. Activate the plugin via *Plugins > Installed plugins*. Activation creates the `ai_bot` role, the sudo tokens table, and grants the `talaxie_release` capabilities to admins/editors.
4. Make sure the WordPress core Abilities API is available (ships with WP 6.9+).
5. To expose the abilities over MCP, install the [WordPress/mcp-adapter](https://github.com/WordPress/mcp-adapter) plugin and activate it.
6. (Optional, dev only) Set `define( 'WP_ENVIRONMENT_TYPE', 'local' );` in `wp-config.php` to expose the test server in addition to the prod server.

== Frequently Asked Questions ==

= Do I need the talaxie theme to use this plugin? =

No. The plugin works with any theme, but the visual rendering is optimized when paired with the official `talaxie` Block Theme.

= Do I need the WordPress/mcp-adapter plugin? =

Only if you want to expose the abilities over MCP to AI clients. The custom post types, taxonomies, and the sudo / audit / dev-mode infrastructure work without it.

= Why a custom `ai_bot` role instead of giving the agent admin? =

Two reasons. First, an admin app password leaked from `~/.claude.json` would compromise the entire site. With `ai_bot` the worst case is publishing a few posts. Second, the principle of least privilege: every elevation is then a deliberate, auditable act rather than ambient authority.

= How do I disable the capability gate during local development? =

Define `TALAXIE_MCP_DEV_MODE` in `wp-config.php`. The plugin honours the constant **only** when `WP_ENVIRONMENT_TYPE` is `local` or `development`; on staging or production it is silently ignored and a permanent admin notice flags the misconfiguration. A red banner is shown in wp-admin while dev-mode is active.

= Where is the source code and bug tracker? =

https://github.com/eric-lemesre/talaxie-wp-core

== Changelog ==

= 0.1.0 =
* Initial release.
* Custom post types `talaxie_release` and `talaxie_contributor`.
* Custom taxonomy `talaxie_component`.
* `ai_bot` role with narrow capabilities; specialized `talaxie_release` capabilities granted to admins/editors.
* Sudo token system: bcrypt-hashed elevation tokens with TTL, scope, optional single-use; admin page, WP-CLI command, REST endpoint.
* MCP capability gate centralised through `Talaxie\Core\Mcp\CapabilityGate`, audited via the `talaxie_mcp_audit` action.
* 30 abilities (Site, Posts, Pages, Media, Releases, Users, Plugins, Network, Generic REST proxy, Audit list).
* Two MCP servers: `talaxie-mcp-prod-server` (production-safe) and `talaxie-mcp-test-server` (full surface, env-gated).
* Audit log via the `talaxie_mcp_audit` post type with a daily retention cron (30 days, filterable).
* `TALAXIE_MCP_DEV_MODE` bypass with environment safety guard.
* Drift-detection endpoint `/wp-json/talaxie-core/v1/mcp/abilities-on-server`.
* Translations: French, German, Spanish.

== Upgrade Notice ==

= 0.1.0 =
First public release. No upgrade path needed.

== Copyright ==

Talaxie Core, (C) 2026 Talaxie Community
Distributed under the terms of the GNU GPL v2 or later.
