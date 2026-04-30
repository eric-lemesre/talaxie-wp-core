# Talaxie Core — companion WordPress plugin

Companion plugin for the [Talaxie](https://talaxie.org) community website.
Talaxie is the open source community fork of Talend Open Studio (Java ETL).
This plugin holds everything that is **not presentational** — every dynamic
feature of the website lives here, while the visual layer lives in the
[`talaxie-wp-theme`](https://github.com/eric-lemesre/talaxie-wp-theme).

## What this plugin provides today

- **Custom post type `talaxie_release`** — Talaxie public releases (binaries,
  changelog, milestone). REST endpoint: `/wp-json/wp/v2/releases`.
- **Custom post type `talaxie_contributor`** — community members (GitHub
  login, role, organization). REST endpoint: `/wp-json/wp/v2/contributors`.
- **Taxonomy `talaxie_component`** — Talaxie components (tCore, tDB*, tFile*,
  tFlow*, ...) attached to releases. REST endpoint:
  `/wp-json/wp/v2/components`.

## What this plugin will provide

- GitHub release syncer (cron-based, pulls releases from the Talaxie GitHub
  organization and creates/updates `talaxie_release` posts).
- Discord stats block (live member count, channel highlights).
- Discourse thread embed block.
- Docusaurus link resolver.
- Custom REST endpoints aggregating data for the website blocks.

These are scaffolded under `src/Integrations/` and `src/REST/`.

## Requirements

- WordPress **6.5** or higher
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
composer run test           # PHPUnit (when test suite exists)
```

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
