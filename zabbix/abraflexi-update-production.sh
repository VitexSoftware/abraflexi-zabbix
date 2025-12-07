#!/bin/bash
set -e
LATEST_PROD=$(curl -s "https://download.flexibee.eu/download/deb-repository/dists/flexibee/non-free/binary-amd64/Packages" 2>/dev/null | \
    awk '/^Package: flexibee$/{p=1} p&&/^Version:/{print $2; exit}')
if [ -z "$LATEST_PROD" ]; then
    echo "unknown"
else
    echo "$LATEST_PROD"
fi
