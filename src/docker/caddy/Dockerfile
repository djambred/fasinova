# Caddy + plugin DNS Cloudflare (untuk sertifikat wildcard lewat DNS challenge).
FROM caddy:2-builder AS build
RUN xcaddy build --with github.com/caddy-dns/cloudflare

FROM caddy:2-alpine
COPY --from=build /usr/bin/caddy /usr/bin/caddy
COPY config.json.tpl /etc/caddy/config.json.tpl
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
