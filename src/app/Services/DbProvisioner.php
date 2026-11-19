<?php

namespace App\Services;

use App\Models\Deployment;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Buat/hapus database + user per karya di server DB khusus karya.
 * Server DB berjalan di daemon rootless `runner` (jaringan fz-db); deployer memegang socket-nya dan memakai `docker exec`.
 */
class DbProvisioner
{
    public const ENGINES = ['postgres' => 'PostgreSQL', 'mariadb' => 'MariaDB'];

    /** Nama aman dari slug: huruf kecil, angka, underscore; maks. 32 karakter. */
    public static function ident(string $slug): string
    {
        return substr('fz_'.preg_replace('/[^a-z0-9]+/', '_', strtolower($slug)), 0, 32);
    }

    /** @return array<string,string> variabel env untuk karya */
    public function provision(Deployment $d): array
    {
        $engine = $d->db_engine;
        $cfg = config("fasinova.karya_db.$engine") ?? throw new \RuntimeException("Engine DB tidak dikenal: $engine");
        $name = self::ident($d->innovation->slug);
        $pass = $d->db_password ?: Str::random(24); // alfanumerik; dipertahankan antar-deploy agar data tetap terhubung

        $this->exec($engine, $engine === 'mariadb'
            ? "CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4;
               CREATE USER IF NOT EXISTS '$name'@'%' IDENTIFIED BY '$pass';
               ALTER USER '$name'@'%' IDENTIFIED BY '$pass';
               GRANT ALL PRIVILEGES ON `$name`.* TO '$name'@'%';"
            : "DO \$\$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$name') THEN CREATE ROLE \"$name\" LOGIN; END IF; END \$\$;
               ALTER ROLE \"$name\" PASSWORD '$pass';
               SELECT 'CREATE DATABASE \"$name\" OWNER \"$name\"' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$name') \\gexec
               REVOKE ALL ON DATABASE \"$name\" FROM PUBLIC;
               REVOKE ALL ON DATABASE postgres FROM PUBLIC; REVOKE ALL ON DATABASE template1 FROM PUBLIC;");
        $d->update(['db_name' => $name, 'db_user' => $name, 'db_password' => $pass]);

        return ['DB_CONNECTION' => $cfg['conn'], 'DB_HOST' => $cfg['container'], 'DB_PORT' => (string) $cfg['port'],
            'DB_DATABASE' => $name, 'DB_USERNAME' => $name, 'DB_PASSWORD' => $pass];
    }

    public function drop(Deployment $d): void
    {
        if (! $d->db_engine || ! $d->db_name) return;
        $n = $d->db_name;
        $this->exec($d->db_engine, $d->db_engine === 'mariadb'
            ? "DROP DATABASE IF EXISTS `$n`; DROP USER IF EXISTS '$n'@'%';"
            : "DROP DATABASE IF EXISTS \"$n\" WITH (FORCE); DROP ROLE IF EXISTS \"$n\";");
        $d->update(['db_name' => null, 'db_user' => null, 'db_password' => null]);
    }

    /** SQL dikirim lewat stdin; kata sandi admin lewat env exec (tidak muncul di daftar proses). */
    private function exec(string $engine, string $sql): void
    {
        $c = config("fasinova.karya_db.$engine");
        $pw = config('fasinova.karya_db.admin_password');
        $cmd = $engine === 'mariadb'
            ? ['docker', 'exec', '-i', '-e', "MYSQL_PWD=$pw", $c['container'], 'mariadb', '-u', $c['admin_user']]
            : ['docker', 'exec', '-i', '-e', "PGPASSWORD=$pw", $c['container'], 'psql', '-U', $c['admin_user'], '-d', 'postgres', '-v', 'ON_ERROR_STOP=1', '-q', '-f', '-'];
        $p = new Process($cmd);
        $p->setInput($sql)->setTimeout(60)->run();
        if (! $p->isSuccessful()) {
            throw new \RuntimeException('Gagal menyiapkan database karya: '.trim(str_replace($pw, '***', $p->getErrorOutput())));
        }
    }
}
