#!/usr/bin/env bash
#
# Builds the installable plugin: netarz-fx-<version>.zip with a netarz-fx/
# folder holding only the plugin files, plus a .sha256 next to it.
#
#   ./build.sh            # writes to ./dist
#   ./build.sh /some/dir  # writes there
#
# It refuses to build when the version in the plugin header, the
# NETARZ_FX_VERSION constant and readme.txt's Stable tag disagree.

set -euo pipefail

cd "$(dirname "$0")"
ROOT=$(pwd)
OUT=${1:-dist}
mkdir -p "$OUT"
OUT=$(cd "$OUT" && pwd)

header=$(sed -n 's/^ \* Version: *//p' netarz-fx.php | tr -d '[:space:]')
constant=$(sed -n "s/^define( 'NETARZ_FX_VERSION', '\([^']*\)' );/\1/p" netarz-fx.php)
stable=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')

if [ -z "$header" ] || [ "$header" != "$constant" ] || [ "$header" != "$stable" ]; then
	echo "Version mismatch: header=$header constant=$constant readme Stable tag=$stable" >&2
	exit 1
fi

# What goes into the plugin. Everything else (README.md, .github, build.sh, ...) stays in the repository.
FILES=(netarz-fx.php uninstall.php readme.txt LICENSE includes assets blocks languages)

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
mkdir "$work/netarz-fx"
for f in "${FILES[@]}"; do
	cp -R "$ROOT/$f" "$work/netarz-fx/"
done
find "$work/netarz-fx" \( -name '.DS_Store' -o -name '*.po~' -o -name '*.orig' \) -delete

# Same input, same ZIP: fixed timestamps (the last commit's, when there is one) and a sorted file list.
stamp=$(git -C "$ROOT" log -1 --format=%ct 2>/dev/null || date +%s)
find "$work/netarz-fx" -exec touch -h -d "@$stamp" {} +

zip_name="netarz-fx-$header.zip"
rm -f "$OUT/$zip_name" "$OUT/$zip_name.sha256"
(cd "$work" && find netarz-fx | LC_ALL=C sort | TZ=UTC zip -X -q "$OUT/$zip_name" -@)
(cd "$OUT" && sha256sum "$zip_name" > "$zip_name.sha256")

echo "Built $OUT/$zip_name"
cat "$OUT/$zip_name.sha256"
