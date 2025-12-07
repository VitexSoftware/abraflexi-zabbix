#!/bin/bash
set -e
LATEST_TESTING=$(curl -s "http://repo.vitexsoftware.cz/dists/borrow/main/binary-amd64/Packages" 2>/dev/null | \
    awk '/^Package: flexibee$/{p=1} p&&/^Version:/{print $2; exit}')
if [ -z "$LATEST_TESTING" ]; then
    echo "unknown"
else
    echo "$LATEST_TESTING"
fi
