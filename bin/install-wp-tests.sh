#!/usr/bin/env bash
# Install the WordPress test suite for talaxie-core integration tests.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
# Standard helper modeled after the WP-CLI scaffold. Creates a dedicated test
# database, downloads the WordPress test library, and writes a config file.

set -euo pipefail

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME="$1"
DB_USER="$2"
DB_PASS="$3"
DB_HOST="${4-localhost}"
WP_VERSION="${5-latest}"
SKIP_DB_CREATE="${6-false}"

WP_TESTS_DIR="${WP_TESTS_DIR-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR-/tmp/wordpress/}"

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -sSL "$1" -o "$2"
	elif command -v wget >/dev/null 2>&1; then
		wget -nv -O "$2" "$1"
	else
		echo "neither curl nor wget is available" >&2
		exit 1
	fi
}

resolve_wp_version() {
	if [[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
		# wordpress-develop tags strip trailing ".0" (the .0 release is
		# tagged as "X.Y", later patches as "X.Y.1", "X.Y.2"...).
		WP_TESTS_REF="${WP_VERSION%.0}"
	elif [[ "$WP_VERSION" == "nightly" || "$WP_VERSION" == "trunk" ]]; then
		WP_TESTS_REF="trunk"
	elif [[ "$WP_VERSION" == "latest" ]]; then
		WP_TESTS_REF="$(download https://api.wordpress.org/core/version-check/1.7/ /dev/stdout | grep -oE '"version":"[^"]+"' | head -n1 | cut -d'"' -f4)"
	else
		echo "unknown WordPress version: $WP_VERSION" >&2
		exit 1
	fi
}

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi
	mkdir -p "$WP_CORE_DIR"
	if [ "$WP_VERSION" == "nightly" ] || [ "$WP_VERSION" == "trunk" ]; then
		mkdir -p /tmp/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip /tmp/wordpress-nightly/wordpress-nightly.zip
		unzip -q /tmp/wordpress-nightly/wordpress-nightly.zip -d /tmp/wordpress-nightly/
		mv /tmp/wordpress-nightly/wordpress/* "$WP_CORE_DIR"
	else
		local archive_version
		if [ "$WP_VERSION" == "latest" ]; then
			archive_version="latest"
		else
			# wordpress.org publishes wordpress-X.Y.tar.gz for the .0 release
			# (no "wordpress-X.Y.0.tar.gz"), and wordpress-X.Y.Z.tar.gz for
			# later patches. Strip the trailing ".0" to land on the right URL.
			archive_version="${WP_VERSION%.0}"
		fi
		download "https://wordpress.org/wordpress-${archive_version}.tar.gz" /tmp/wordpress.tar.gz
		tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
	fi

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php" || true
}

install_test_suite() {
	local archive_url branch_or_tag
	if [ "$WP_TESTS_REF" = "trunk" ]; then
		branch_or_tag="trunk"
		archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz"
	else
		branch_or_tag="${WP_TESTS_REF}"
		archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_TESTS_REF}.tar.gz"
	fi

	if [ ! -d "$WP_TESTS_DIR/includes" ]; then
		mkdir -p "$WP_TESTS_DIR"
		local archive="/tmp/wordpress-develop-${branch_or_tag}.tar.gz"
		download "$archive_url" "$archive"
		tar -xzf "$archive" -C /tmp
		local extracted="/tmp/wordpress-develop-${branch_or_tag}"
		cp -r "$extracted/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
		cp -r "$extracted/tests/phpunit/data" "$WP_TESTS_DIR/data"
		cp "$extracted/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config-sample.php"
	fi

	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		cp "$WP_TESTS_DIR/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		local sed_inplace=( -i )
		if [[ "$OSTYPE" == "darwin"* ]]; then
			sed_inplace=( -i '' )
		fi
		sed "${sed_inplace[@]}" "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed "${sed_inplace[@]}" "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

create_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		return
	fi
	local protocol port host
	host="${DB_HOST%%:*}"
	if [[ "$DB_HOST" == *:* ]]; then
		port="${DB_HOST##*:}"
	else
		port=""
	fi
	local mysql_args=( -u"$DB_USER" -h"$host" )
	if [ -n "$port" ]; then
		mysql_args+=( -P"$port" )
	fi
	if [ -n "$DB_PASS" ]; then
		mysql_args+=( -p"$DB_PASS" )
	fi
	mysqladmin "${mysql_args[@]}" create "$DB_NAME" --force
}

resolve_wp_version
install_wp
install_test_suite
create_db

echo "WordPress test suite installed at $WP_TESTS_DIR"
echo "WordPress core at $WP_CORE_DIR"
echo "Test database: $DB_NAME"
