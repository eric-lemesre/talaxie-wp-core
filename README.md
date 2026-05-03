# Talaxie Core — companion WordPress plugin

Companion plugin for the [Talaxie](https://talaxie.org) community website.
Talaxie is the open source community fork of Talend Open Studio (Java ETL).
This plugin holds everything that is **not presentational** — every dynamic
feature of the website lives here, while the visual layer lives in the
[`talaxie-wp-theme`](https://github.com/eric-lemesre/talaxie-wp-theme).

## What this plugin provides today

### Content model

- **Custom post type `talaxie_release`** — Talaxie public releases (binaries,
  changelog, milestone). Specialized capabilities (`edit_talaxie_release`,
  `delete_talaxie_releases`, etc.) granted to admins, editors and the
  `ai_bot` role. REST endpoint: `/wp-json/wp/v2/releases`.
- **Custom post type `talaxie_contributor`** — community members (GitHub
  login, role, organization). REST endpoint: `/wp-json/wp/v2/contributors`.
- **Taxonomy `talaxie_component`** — Talaxie components attached to
  releases. REST endpoint: `/wp-json/wp/v2/components`.

### MCP surface (sudo-style)

The plugin exposes the WordPress site to AI agents through the
[Model Context Protocol](https://modelcontextprotocol.io), with a Unix
sudo-inspired access model.

- **Role `ai_bot`** with narrow capabilities: read, edit posts/pages,
  upload files, full CRUD on Talaxie CPTs. Anything beyond that — site
  options, plugin activation, user management, deletion of regular
  posts — requires a sudo token.
- **Sudo tokens** are short-lived (default 15 min, max 60 min),
  scoped to a list of capabilities, hashed with bcrypt in a custom
  `*_talaxie_sudo_tokens` table. Generate them via:
  - **wp-admin** — *Tools > MCP Sudo* (form + nonce, displayed once)
  - **WP-CLI** — `wp talaxie mcp sudo-token --scope=manage_options [--ttl=15m]`
  - **REST** — `POST /wp-json/talaxie-core/v1/mcp/sudo-token` (caller
    must already hold `manage_options`)
- **Capability gate** — every ability declares the capability it
  requires. Calls without the capability or a matching sudo token are
  denied and audited.
- **Audit log** — non-public CPT `talaxie_mcp_audit`, every gate
  decision is recorded (ability, capability, allowed/denied, sudo
  used, IP). Daily cron purges entries older than 30 days
  (filterable). The `talaxie-core/audit-list` ability exposes it.
- **Two MCP servers** — `talaxie-mcp-prod-server` (production-safe
  abilities only) is always exposed. `talaxie-mcp-test-server` (every
  ability, including destructive ones) is added when
  `WP_ENVIRONMENT_TYPE` is `local` or `development`.
- **Drift detection** — `GET /wp-json/talaxie-core/v1/mcp/abilities-on-server`
  reports the tools each MCP server *actually* exposes and flags any
  ability with `is_allowed_on_production() = false` that leaked onto
  the prod server.
- **Dev-mode bypass** (optional, off by default) — defining
  `TALAXIE_MCP_DEV_MODE` in `wp-config.php` disables the gate. Only
  honored when `WP_ENVIRONMENT_TYPE` is `local`/`development`. A
  permanent red admin notice flags an active dev-mode.

The available abilities cover Site (info, options), Posts CRUD,
Pages CRUD, Media (list/upload/delete), Releases CRUD, Users CRUD,
Plugins (list/activate), multisite Network (create/delete site) and
a generic `wp/v2/*` REST proxy as a fallback.

## Requirements

- WordPress **6.9** or higher (the Abilities API ships with 6.9 core)
- PHP **8.1** or higher

## Install (development)

```bash
cd <wordpress-root>/wp-content/plugins
git clone https://github.com/eric-lemesre/talaxie-wp-core.git talaxie-core
cd talaxie-core
composer install
wp plugin activate talaxie-core
```

## Development

```bash
composer run lint           # PHPCS (WordPress Coding Standards)
composer run lint:fix       # PHPCBF auto-fix
composer run test           # PHPUnit integration suite
```

### Running the test suite locally

The PHPUnit suite is integration-level (boots the WP test library and
hits a real database). One-time setup:

```bash
# Create a dedicated test DB and grant access to the dev user
sudo mysql -e "CREATE DATABASE IF NOT EXISTS talaxie_test CHARACTER SET utf8mb4;"
sudo mysql -e "GRANT ALL ON talaxie_test.* TO 'talaxie_dev'@'localhost'; FLUSH PRIVILEGES;"

# Download the WP test library + bootstrap config
bin/install-wp-tests.sh talaxie_test talaxie_dev '<password>' localhost latest true
```

Then on every run:

```bash
composer install            # PHPUnit 9.6 + Yoast polyfills 2.0
composer run test
```

CI (GitHub Actions) runs the suite on a matrix of PHP 8.1/8.2/8.3 and
WordPress 6.5 / latest.

## Architecture

- PSR-4 namespace `Talaxie\Core\` autoloaded from `src/`
- Bootstrap is performed by `src/Plugin.php` via `register_activation_hook`,
  `register_deactivation_hook` and the `init` action.
- Each subsystem is a small class under `src/PostTypes/`, `src/Taxonomies/`,
  `src/Integrations/<Service>/`, `src/REST/`.

## Companion theme

The [`talaxie-wp-theme`](https://github.com/eric-lemesre/talaxie-wp-theme)
is the Block Theme that displays content produced by this plugin. The two
are loosely coupled: the plugin runs without the theme, and the theme
gracefully degrades without the plugin.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE).

## Contributing

Pull requests welcome. See `AGENTS.md` for project conventions and the
folder map.
