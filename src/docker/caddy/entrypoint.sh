#!/bin/sh
# Menyusun config dari templat. CADDY_TLS=cloudflare (produksi, wildcard via DNS challenge) | internal (dev, CA lokal Caddy).
set -eu
: "${APP_DOMAIN:?APP_DOMAIN wajib diisi}"
if [ "${CADDY_TLS:-cloudflare}" = "internal" ]; then
  POLICY='{"issuers":[{"module":"internal"}]}'
else
  : "${CLOUDFLARE_API_TOKEN:?CLOUDFLARE_API_TOKEN wajib diisi}"
  POLICY='{"subjects":["*.'$APP_DOMAIN'","'$APP_DOMAIN'","www.'$APP_DOMAIN'"],"issuers":[{"module":"acme","challenges":{"dns":{"provider":{"name":"cloudflare","api_token":"{env.CLOUDFLARE_API_TOKEN}"}}}}]}'
fi
mkdir -p /run/fasinova
sed -e "s|__DOMAIN__|$APP_DOMAIN|g" -e "s|__TLS_POLICY__|$POLICY|" /etc/caddy/config.json.tpl > /tmp/config.json
exec caddy run --config /tmp/config.json
