#!/usr/bin/env bash
# Checks that every place the version is written agrees, and that the readme has a changelog entry.
# Usage: bin/check-version.sh [expected-version]
set -euo pipefail
cd "$(dirname "$0")/.."

header=$(sed -n 's/^ \* Version: *//p' csi-cookie-consent.php | tr -d '[:space:]')
const=$(sed -n "s/.*define( 'GDPRCN_VERSION', '\([^']*\)'.*/\1/p" csi-cookie-consent.php)
stable=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')
expected=${1:-$header}

fail=0
for pair in "plugin header:$header" "GDPRCN_VERSION:$const" "readme Stable tag:$stable"; do
	if [ "${pair##*:}" != "$expected" ]; then
		echo "::error::${pair%%:*} is ${pair##*:}, expected $expected"
		fail=1
	fi
done
if ! grep -q "^= $expected =$" readme.txt; then
	echo "::error::readme.txt has no changelog entry '= $expected ='"
	fail=1
fi

[ "$fail" = 0 ] && echo "Version $expected is consistent."
exit "$fail"
