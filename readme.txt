=== Talaxie Core ===
Contributors: talaxiecommunity
Tags: cpt, custom-post-type, taxonomy, etl, integration
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Companion plugin for the Talaxie community website. Provides custom post types and integrations.

== Description ==

Talaxie Core is the companion plugin to the Talaxie WordPress theme. It carries all the data layer of the community website: custom post types for releases and contributors, a taxonomy for Talaxie components, and the entry points for integrations with GitHub, Discord and Discourse.

The plugin is presentation-agnostic. It works with any theme, but is designed to render at its best with the official `talaxie` theme.

= What is Talaxie? =

Talaxie is the open source community fork of Talend Open Studio (ETL), born after Qlik discontinued the free version of Talend.

= Custom post types =

* `talaxie_release` — public Talaxie releases.
* `talaxie_contributor` — community members.

= Taxonomies =

* `talaxie_component` — Talaxie components attached to releases (tCore, tDBInput, tFileInputDelimited, ...).

== Installation ==

1. Upload the plugin folder `talaxie-core` to `/wp-content/plugins/`.
2. Run `composer install` inside the plugin folder.
3. Activate the plugin via `Plugins > Installed plugins`.

== Frequently Asked Questions ==

= Do I need the talaxie theme to use this plugin? =

No. The plugin works with any theme, but the visual rendering is optimized when paired with the official `talaxie` Block Theme.

= Where is the source code? =

https://github.com/eric-lemesre/talaxie-wp-core

== Changelog ==

= 0.1.0 =
* Initial release: custom post types `talaxie_release` and `talaxie_contributor`, custom taxonomy `talaxie_component`, plugin scaffolding for integrations and REST endpoints.

== Copyright ==

Talaxie Core, (C) 2026 Talaxie Community
Distributed under the terms of the GNU GPL v2 or later.
