# AGENTS.md — talaxie-wp-core

> Onboarding for AI agents working on the Talaxie WordPress companion plugin.

## Project nature

Companion plugin for the Talaxie community website (https://talaxie.org).
Talaxie itself is the **open source community fork of Talend Open Studio**
(Java ETL). This plugin holds every dynamic / data feature of the site —
the [`talaxie-wp-theme`](https://github.com/eric-lemesre/talaxie-wp-theme)
holds only the presentation layer.

## Key facts

- **Type**: WordPress plugin.
- **Slug**: `talaxie-core` (folder name in `wp-content/plugins/talaxie-core/`).
- **Text domain**: `talaxie-core`.
- **PHP namespace**: `Talaxie\Core\` (PSR-4, autoloaded via Composer).
- **License**: GPL-2.0-or-later.
- **Min WordPress**: 6.9 (the Abilities API ships with 6.9 core).
- **Min PHP**: 8.1.
- **Distribution**: GitHub + planned WordPress.org submission.

## Repository layout

```
talaxie-core/
├── talaxie-core.php          ← plugin header + bootstrap (loads vendor/autoload.php)
├── uninstall.php             ← cleanup on plugin uninstallation
├── readme.txt                ← WP.org format readme
├── README.md                 ← GitHub readme (developer audience)
├── LICENSE                   ← GPL-2.0
├── composer.json             ← PSR-4 autoload + dev dependencies
├── .phpcs.xml.dist           ← WordPress Coding Standards configuration
├── src/
│   ├── Plugin.php            ← bootstrap class (Talaxie\Core\Plugin)
│   ├── PostTypes/
│   │   ├── Release.php       ← talaxie_release CPT
│   │   └── Contributor.php   ← talaxie_contributor CPT
│   ├── Taxonomies/
│   │   └── Component.php     ← talaxie_component taxonomy
│   ├── Integrations/         ← (to be filled) GitHub, Discord, Discourse
│   │   └── GitHub/
│   └── REST/                 ← (to be filled) custom REST endpoints
├── languages/                ← .pot template (text-domain talaxie-core)
└── .github/workflows/        ← CI: WPCS lint + integration smoke test
```

## Non-negotiable conventions

- **PSR-4 only**: every PHP class lives under `src/` with the namespace
  `Talaxie\Core\<SubNamespace>\`. No global procedural code in `src/`.
- **Strict types**: every file in `src/` declares `declare(strict_types=1);`.
- **Internationalization**: every user-facing string must be wrapped in
  `__()` / `esc_html__()` etc. with the text-domain `talaxie-core`.
- **Prefix everything**: post types, taxonomies, options, transients, hooks
  must start with `talaxie_` to avoid collisions. Constants start with
  `TALAXIE_CORE_`.
- **Capability checks** on every admin / write endpoint. Default to
  `current_user_can( 'edit_posts' )` (post-grade) or `manage_options`
  (settings-grade).
- **Nonces** on every form submission, REST mutation and AJAX handler.
- **Composer autoload**: never `require` PHP class files manually outside
  of `talaxie-core.php`. Always rely on PSR-4.
- **No external CDN at runtime**: all assets shipped in the plugin folder.
- **No AI agent attribution** in commits, PRs, code or documentation
  (this `AGENTS.md` is the only legitimate exception).

## Local development

The plugin is meant to live at `wp-content/plugins/talaxie-core/`:

```bash
cd <wordpress-root>/wp-content/plugins
git clone git@github.com:eric-lemesre/talaxie-wp-core.git talaxie-core
cd talaxie-core
composer install
wp plugin activate talaxie-core
```

The umbrella project `Talaxie-Project` provides a `bin/bootstrap.sh`
script that automates this for the dev environment.

## Useful commands

```bash
# WordPress Coding Standards
composer run lint
composer run lint:fix

# PHPUnit (once tests are added)
composer run test

# Generate / update translations template
wp i18n make-pot . languages/talaxie-core.pot
```

## Recommended skills

| Skill | Use case |
|---|---|
| `security-review` | Plugin manipulates data, secrets, REST endpoints — always review |
| `owasp-security-check` | API surface audit |
| `find-skills` | Discover additional helpers when needed |

## Pitfalls to avoid

- **Do not put presentation code here.** Templates, CSS, block markup live
  in `talaxie-wp-theme`. The plugin only exposes data and behavior.
- **Do not hardcode credentials** for GitHub / Discord / Discourse. Use the
  WordPress options API for non-secret config and the WordPress secrets
  approach (constants in `wp-config.php`) for tokens.
- **Do not flush rewrite rules** outside `register_activation_hook` or a
  one-shot maintenance command. It is expensive and runs on every page
  load if mishandled.
- **Do not use `WP_Query` in REST handlers without nonce + capability
  checks** — REST endpoints are public by default.
- **Do not commit `vendor/`** or any local credentials.
