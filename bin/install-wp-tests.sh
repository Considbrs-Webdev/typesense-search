#!/usr/bin/env bash
#
# Install WordPress and the PHPUnit test library for integration tests.
#
# Usage:
#   bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
#
set -euo pipefail

if [[ $# -lt 3 ]]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]"
	exit 1
fi

DB_NAME="${1}"
DB_USER="${2}"
DB_PASS="${3}"
DB_HOST="${4:-127.0.0.1:3307}"
WP_VERSION="${5:-6.7.2}"
SKIP_DB_CREATE="${6:-false}"

TMPDIR="${WP_TESTS_TMPDIR:-/tmp/typesense-search-wp-tests}"
WP_CORE_DIR="${TMPDIR}/wordpress"
WP_TESTS_DIR="${TMPDIR}/wordpress-tests-lib"

download() {
	if command -v curl >/dev/null 2>&1; then
		curl -fsSL "$1" -o "$2"
	else
		wget -q "$1" -O "$2"
	fi
}

if [[ "${SKIP_DB_CREATE}" != "true" ]]; then
	EXTRA=()
	if [[ "${DB_HOST}" == *:* ]]; then
		IFS=':' read -r DB_HOST_ONLY DB_PORT <<< "${DB_HOST}"
		EXTRA=(--host="${DB_HOST_ONLY}" --port="${DB_PORT}" --protocol=tcp)
	else
		EXTRA=(--host="${DB_HOST}" --protocol=tcp)
	fi
	mysqladmin create "${DB_NAME}" --user="${DB_USER}" --password="${DB_PASS}" "${EXTRA[@]}" 2>/dev/null || true
fi

if [[ ! -d "${WP_TESTS_DIR}" ]]; then
	mkdir -p "${TMPDIR}"
	echo "Installing WordPress PHPUnit library ${WP_VERSION} into ${WP_TESTS_DIR}"
	svn co --quiet "https://develop.svn.wordpress.org/tags/${WP_VERSION}/tests/phpunit" "${WP_TESTS_DIR}"
fi

if [[ ! -d "${WP_CORE_DIR}" ]]; then
	mkdir -p "${WP_CORE_DIR}"
	echo "Downloading WordPress ${WP_VERSION} into ${WP_CORE_DIR}"
	download "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" "${TMPDIR}/wordpress.tar.gz"
	tar --strip-components=1 -xf "${TMPDIR}/wordpress.tar.gz" -C "${WP_CORE_DIR}"
	rm -f "${TMPDIR}/wordpress.tar.gz"
fi

PLUGIN_SLUG="typesense-search"
PLUGIN_LINK="${WP_CORE_DIR}/wp-content/plugins/${PLUGIN_SLUG}"
mkdir -p "${WP_CORE_DIR}/wp-content/plugins"
ln -sfn "$(cd "$(dirname "$0")/.." && pwd)" "${PLUGIN_LINK}"

WP_TESTS_CONFIG="${WP_TESTS_DIR}/wp-tests-config.php"
if [[ ! -f "${WP_TESTS_CONFIG}" ]]; then
	download "https://develop.svn.wordpress.org/tags/${WP_VERSION}/wp-tests-config-sample.php" "${WP_TESTS_CONFIG}"
	sed -i "s/youremptytestdbnamehere/${DB_NAME}/" "${WP_TESTS_CONFIG}"
	sed -i "s/yourusernamehere/${DB_USER}/" "${WP_TESTS_CONFIG}"
	sed -i "s/yourpasswordhere/${DB_PASS}/" "${WP_TESTS_CONFIG}"
	sed -i "s|localhost|${DB_HOST}|" "${WP_TESTS_CONFIG}"
	sed -i "s/'WP_TESTS_DOMAIN', 'example.org'/'WP_TESTS_DOMAIN', 'example.test'/" "${WP_TESTS_CONFIG}"
	sed -i "s/'WP_TESTS_EMAIL', 'admin@example.org'/'WP_TESTS_EMAIL', 'admin@example.test'/" "${WP_TESTS_CONFIG}"
	sed -i "s/'WP_TESTS_TITLE', 'Test Blog'/'WP_TESTS_TITLE', 'Typesense Search Integration Tests'/" "${WP_TESTS_CONFIG}"
	PHP_BIN="$(command -v php)"
	sed -i "s#'WP_PHP_BINARY', 'php'#'WP_PHP_BINARY', '${PHP_BIN}'#" "${WP_TESTS_CONFIG}"
	sed -i "s|define( 'ABSPATH', dirname( __FILE__ ) . '/src/' );|define( 'ABSPATH', '${WP_CORE_DIR}/' );|" "${WP_TESTS_CONFIG}"
fi

chmod +x "$(dirname "$0")/install-wp-tests.sh" 2>/dev/null || true

echo "WP_TESTS_DIR=${WP_TESTS_DIR}"
echo "WP_CORE_DIR=${WP_CORE_DIR}"
