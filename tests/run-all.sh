#!/bin/bash
# Runs every test suite. Exits non-zero if any suite fails or will not run.
cd "$(dirname "$0")/.." || exit 2
status=0
for t in tests/test-*.php; do
	printf '%s: ' "$t"
	out=$(php "$t" 2>&1)
	code=$?
	echo "$out" | tail -1
	if [ "$code" -ne 0 ]; then
		echo "$out" | head -20
		status=1
	fi
done
exit $status
