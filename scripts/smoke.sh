#!/usr/bin/env bash
#
# Installs the plugin from dist/parolas/ (npm run dist) in a clean
# wp-env and checks that it works: activation, a linked post that prints the
# component with its assets, the settings page and an empty debug.log. With
# "latest" it also runs Plugin Check on the installed copy.
#
# Usage: scripts/smoke.sh <6.5|latest>
#
# It replaces .wp-env.override.json while it runs and restores it at the end,
# and it empties the wp-env databases.

set -euo pipefail

case "${1:-}" in
	6.5) core='"WordPress/WordPress#6.5"' php='7.4' ;;
	latest) core='null' php='8.3' ;;
	*)
		echo "Usage: scripts/smoke.sh <6.5|latest>" >&2
		exit 2
		;;
esac
target="$1"

cd "$(dirname "$0")/.."

if [ ! -f dist/parolas/parolas.php ]; then
	echo "dist/parolas/ not found: run \`npm run dist\` first." >&2
	exit 1
fi

wp_env() {
	npx wp-env "$@"
}

cli() {
	wp_env run cli "$@" 2>/dev/null
}

fail() {
	echo "FAIL ($target): $*" >&2
	exit 1
}

# Keep the developer's override and put it back however the script ends.
override=.wp-env.override.json
backup=''
if [ -f "$override" ]; then
	backup="$(mktemp)"
	cp "$override" "$backup"
fi
restore() {
	if [ -n "$backup" ]; then
		cp "$backup" "$override"
		rm -f "$backup"
	else
		rm -f "$override"
	fi
}
trap restore EXIT

# Same mappings key as .wp-env.json, so the zip replaces the repository
# instead of being mounted next to it.
cat > "$override" <<JSON
{
	"core": $core,
	"phpVersion": "$php",
	"mappings": {
		"wp-content/plugins/parolas": "./dist/parolas"
	},
	"config": {
		"WP_DEBUG": true,
		"WP_DEBUG_LOG": true,
		"WP_DEBUG_DISPLAY": false
	}
}
JSON

echo "== Starting wp-env ($target, PHP $php)"
# Recreate the containers: npm run dist replaces dist/parolas/, and
# running containers would keep the mount of the deleted directory.
wp_env stop >/dev/null 2>&1 || true
for attempt in 1 2 3; do
	if wp_env start; then
		break
	fi
	[ "$attempt" -eq 3 ] && fail "wp-env did not start"
	echo "wp-env start failed, retrying in 15 s ($attempt/3)"
	sleep 15
done
wp_env reset all

echo "== Activating the plugin"
cli wp plugin activate parolas
[ "$target" = latest ] && cli wp plugin activate plugin-check
cli rm -f wp-content/debug.log

site="$(cli wp option get siteurl | tr -d '\r')"

echo "== Linked post"
post_id="$(cli wp post create --post_type=post --post_status=publish \
	--post_title='Smoke test' --post_content='Smoke test.' --porcelain |
	tr -d '\r' | tail -n 1)"
[[ "$post_id" =~ ^[0-9]+$ ]] || fail "could not create the post"
cli wp post meta update "$post_id" _atproto_thread_uri \
	'at://did:plc:t3q3ylsnsser3o74xz42sdqk/app.bsky.feed.post/3my3zz36oe42v' >/dev/null

page="$(curl -fsSL "$site/?p=$post_id")" || fail "the post did not load"
grep -q '<atproto-comments' <<<"$page" || fail "the post does not print <atproto-comments>"

check_asset() {
	local url
	url="$(grep -oE "https?://[^\"' ]+/$1[^\"' ]*" <<<"$2" | head -n 1 || true)"
	[ -n "$url" ] || fail "$1 is not referenced"
	url="${url//&#038;/&}"
	[ "$(curl -s -o /dev/null -w '%{http_code}' "$url")" = 200 ] ||
		fail "$1 does not respond 200 ($url)"
	echo "ok $1"
}
check_asset 'assets/vendor/atproto-comments/index.js' "$page"
check_asset 'assets/vendor/atproto-comments/atproto-comments.css' "$page"

echo "== Settings page"
jar="$(mktemp)"
curl -fsS -o /dev/null -c "$jar" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
	--data-urlencode 'log=admin' --data-urlencode 'pwd=password' \
	--data-urlencode 'testcookie=1' "$site/wp-login.php" ||
	fail "could not log in"
settings="$(curl -fsS -b "$jar" "$site/wp-admin/options-general.php?page=atproto-comments")" ||
	fail "the settings page did not load"
rm -f "$jar"
grep -q '<h1>Parolas</h1>' <<<"$settings" || fail "the settings page has no title"
check_asset 'build/settings.js' "$settings"

echo "== debug.log"
log="$(cli sh -c 'cat wp-content/debug.log 2>/dev/null || true')"
if [ -n "$log" ]; then
	echo "$log" >&2
	fail "debug.log is not empty"
fi
echo "ok empty"

if [ "$target" = latest ]; then
	echo "== Plugin Check"
	report="$(cli wp plugin check parolas --ignore-warnings --format=csv)"
	if grep -q ',ERROR,' <<<"$report"; then
		echo "$report" >&2
		fail "Plugin Check reports errors"
	fi
	echo "ok no errors"
fi

echo "PASS ($target)"
