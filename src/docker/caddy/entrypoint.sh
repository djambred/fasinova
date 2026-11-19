#!/bin/sh
# Menyusun config dari templat. Mode TLS (CADDY_TLS):
#   cloudflare : wildcard *.domain via DNS challenge Cloudflare (butuh CLOUDFLARE_API_TOKEN; DNS domain harus di Cloudflare)
#   acme       : sertifikat per-hostname via ACME HTTP-01/TLS-ALPN (tanpa token; butuh record A untuk apex dan tiap subdomain/wildcard `*`)
#   internal   : CA lokal Caddy (uji)
set -eu
: "${APP_DOMAIN:?APP_DOMAIN wajib diisi}"
MODE="${CADDY_TLS:-cloudflare}"
WWW=',"www.'$APP_DOMAIN'"'
KARYA_MATCH='"match": [{ "host": ["*.'$APP_DOMAIN'"] }],'
case "$MODE" in
  internal) POLICY='{"issuers":[{"module":"internal"}]}' ;;
  acme)
    POLICY=''
    # Tanpa wildcard cert: rute karya (host persis) mendapat sertifikatnya sendiri; rute 404 tanpa matcher host agar tidak diminta sertifikat wildcard.
    KARYA_MATCH=''
    [ "${CADDY_WWW:-0}" = "1" ] || WWW='' ;;
  *)
    : "${CLOUDFLARE_API_TOKEN:?CLOUDFLARE_API_TOKEN wajib diisi}"
    POLICY='{"subjects":["*.'$APP_DOMAIN'","'$APP_DOMAIN'","www.'$APP_DOMAIN'"],"issuers":[{"module":"acme","challenges":{"dns":{"provider":{"name":"cloudflare","api_token":"{env.CLOUDFLARE_API_TOKEN}"}}}}]}' ;;
esac
mkdir -p /run/fasinova
sed -e "s|__DOMAIN__|$APP_DOMAIN|g" -e "s|__TLS_POLICY__|$POLICY|" -e "s|__WWW__|$WWW|" -e "s|__KARYA_MATCH__|$KARYA_MATCH|" /etc/caddy/config.json.tpl > /tmp/config.json
exec caddy run --config /tmp/config.json
