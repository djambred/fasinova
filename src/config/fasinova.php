<?php

return [
    'domain' => env('APP_DOMAIN', 'fasinova.test'),
    'github_org' => env('GITHUB_ORG'),
    'github_token' => env('GITHUB_TOKEN'),
    'port_range' => env('DEPLOY_PORT_RANGE', '20000-20999'),
    'apps_dir' => env('RUNNER_APPS_DIR', '/home/runner/apps'),
    'health_host' => env('HEALTH_HOST', '127.0.0.1'),
    'caddy_socket' => env('CADDY_SOCKET', '/run/fasinova/caddy-admin.sock'),
    'cpu_limit' => 0.5,
    'mem_limit_mb' => 512,
    'turnstile_site_key' => env('TURNSTILE_SITE_KEY'),
    'turnstile_secret' => env('TURNSTILE_SECRET'),
    // Database karya: container di daemon rootless `runner`, jaringan `fz-db`. Container karya memakai nama container sebagai host.
    'karya_db' => [
        'network' => 'fz-db',
        'admin_password' => env('KARYA_DB_ROOT_PASSWORD'),
        'mariadb' => ['container' => 'karya-mariadb', 'port' => 3306, 'admin_user' => 'root', 'conn' => 'mysql'],
        'postgres' => ['container' => 'karya-postgres', 'port' => 5432, 'admin_user' => 'karya_admin', 'conn' => 'pgsql'],
    ],
    // Bentuk karya. Hanya `web` yang punya alur deploy otomatis (stack, database, repo, env, subdomain); bentuk lain belum dibangun.
    'form_types' => [
        'web' => ['label' => 'Aplikasi web (demo online)', 'deploy' => true],
        'mobile' => ['label' => 'Aplikasi mobile', 'deploy' => false],
        'iot' => ['label' => 'Perangkat IoT / Robotik', 'deploy' => false],
        'model' => ['label' => 'Model / eksperimen riset (tanpa demo online)', 'deploy' => false],
        'other' => ['label' => 'Lainnya', 'deploy' => false],
    ],
    // Pilihan stack mahasiswa; port hanya nilai awal (boleh diubah di form).
    'stacks' => [
        'js' => ['label' => 'JavaScript (Node)', 'port' => 3000],
        'laravel' => ['label' => 'Laravel (PHP)', 'port' => 8000],
        'streamlit' => ['label' => 'Python (Streamlit)', 'port' => 8501],
    ],
    // Kata terlarang di subdomain (selain reserved_subdomains); sebagian pencocokan substring. [Menebak] — lengkapi sesuai kebijakan prodi.
    'forbidden_subdomain_words' => ['porn', 'xxx', 'judi', 'slot', 'togel', 'bokep'],
    'reserved_subdomains' => ['www', 'admin', 'api', 'mail', 'panel', 'app', 'sales'],
    'max_services' => 5,
    'allowed_caps' => ['CHOWN', 'SETUID', 'SETGID'],
    'revision_days' => 7,
    // false: rahasia di repo publik hanya diperingatkan (deploy lanjut); true: deploy dibatalkan.
    'gitleaks_block' => (bool) env('GITLEAKS_BLOCK', false),
    'probe_image' => env('PROBE_IMAGE', 'curlimages/curl:8.10.1'),
    'build_timeout' => 900,
    'claim_phrases' => ['100% aman', 'tidak bisa diretas', 'pasti', 'selalu', 'menggantikan manusia'],
];
