{
  "admin": { "listen": "unix//run/fasinova/caddy-admin.sock|0666" },
  "apps": {
    "http": {
      "servers": {
        "redir": {
          "listen": [":80"],
          "routes": [{ "handle": [{ "handler": "static_response", "status_code": 308, "headers": { "Location": ["https://{http.request.host}{http.request.uri}"] } }] }]
        },
        "main": {
          "@id": "fz-server",
          "listen": [":443"],
          "routes": [
            {
              "match": [{ "host": ["__DOMAIN__"__WWW__] }],
              "handle": [{ "handler": "reverse_proxy", "upstreams": [{ "dial": "127.0.0.1:8080" }] }],
              "terminal": true
            },
            {
              __KARYA_MATCH__
              "handle": [{ "handler": "static_response", "status_code": 404, "body": "Karya tidak ditemukan" }],
              "terminal": true
            }
          ]
        }
      }
    },
    "tls": { "automation": { "policies": [__TLS_POLICY__] } }
  }
}
