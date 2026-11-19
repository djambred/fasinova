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
    // Kapabilitas dasar yang SELALU diberikan (setelah cap_drop ALL): image resmi (nginx, mariadb, postgres, php-fpm)
    // butuh ini untuk chown folder data dan turun hak ke user non-root saat start. Di Docker rootless, kapabilitas ini
    // hanya berlaku di dalam user namespace (bukan root di host).
    'base_caps' => ['CHOWN', 'SETUID', 'SETGID', 'FOWNER', 'DAC_OVERRIDE'],
    // Tambahan yang boleh dipilih PIC per karya (selain base_caps) dan yang boleh dideklarasikan di compose mahasiswa.
    'allowed_caps' => ['CHOWN', 'SETUID', 'SETGID', 'FOWNER', 'DAC_OVERRIDE', 'NET_BIND_SERVICE', 'SYS_CHROOT', 'KILL', 'SETPCAP', 'FSETID'],
    'revision_days' => 7,
    // false: rahasia di repo publik hanya diperingatkan (deploy lanjut); true: deploy dibatalkan.
    'gitleaks_block' => (bool) env('GITLEAKS_BLOCK', false),
    // Jendela tunggu health check: percobaan x 6 detik (50 = ±5 menit). Aplikasi yang memasang dependensi saat start butuh >1 menit.
    'health_attempts' => (int) env('HEALTH_ATTEMPTS', 50),
    'probe_image' => env('PROBE_IMAGE', 'curlimages/curl:8.10.1'),
    'build_timeout' => 900,
    'claim_phrases' => ['100% aman', 'tidak bisa diretas', 'pasti', 'selalu', 'menggantikan manusia'],
];
