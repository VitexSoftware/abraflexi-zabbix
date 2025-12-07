#!/bin/bash

# Zabbix agent script to check for AbraFlexi updates (testing and production)
# Returns JSON with current version, latest testing, latest production, and update flags


set -e


# Fetch latest testing version
LATEST_TESTING=$(curl -s "http://repo.vitexsoftware.cz/dists/borrow/main/binary-amd64/Packages" 2>/dev/null | \
    awk '/^Package: flexibee$/{p=1} p&&/^Version:/{print $2; exit}')

# Fetch latest production version (corrected URL and section)
LATEST_PROD=$(curl -s "https://download.flexibee.eu/download/deb-repository/dists/flexibee/non-free/binary-amd64/Packages" 2>/dev/null | \
    awk '/^Package: flexibee$/{p=1} p&&/^Version:/{print $2; exit}')
# If no version found, set to unknown
if [ -z "$LATEST_TESTING" ] || [ "$LATEST_TESTING" = "" ]; then
    LATEST_TESTING="unknown"
fi
if [ -z "$LATEST_PROD" ] || [ "$LATEST_PROD" = "" ]; then
    LATEST_PROD="unknown"
fi



# Return JSON
cat <<EOF
{
    "latestTesting": "$LATEST_TESTING",
    "latestProduction": "$LATEST_PROD"
}
EOF
