#!/usr/bin/env bash
#
# Trust the Caddy local root CA so the browser stops showing
# "connection is not private" for https://friendly-fyzio.test.
#
# The CA lives in the persistent `sail-caddy-data` Docker volume and only
# changes if that volume is destroyed (`docker compose down -v`). Re-run this
# script after that, then fully quit and reopen your browser.
#
# Usage: ./docker/caddy/trust-ca.sh   (will prompt for your sudo password)

set -euo pipefail

CONTAINER="friendly-fyzio-app-caddy-1"
CA_PATH="/data/caddy/pki/authorities/local/root.crt"
TMP_CA="$(mktemp -t caddy-root-XXXX).crt"

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    echo "Caddy container '${CONTAINER}' is not running. Start it with: vendor/bin/sail up -d" >&2
    exit 1
fi

echo "Exporting Caddy root CA from container..."
docker exec "${CONTAINER}" cat "${CA_PATH}" > "${TMP_CA}"

echo "Adding CA to the System keychain as a trusted root (requires sudo)..."
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain "${TMP_CA}"

rm -f "${TMP_CA}"

TRUSTED=$(security find-certificate -a -c "Caddy Local Authority" /Library/Keychains/System.keychain | grep -c Caddy || true)
echo "Done. Trusted 'Caddy Local Authority' certificates in System keychain: ${TRUSTED}"
echo "Now fully quit and reopen your browser."
