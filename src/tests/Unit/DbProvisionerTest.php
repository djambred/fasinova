<?php

namespace Tests\Unit;

use App\Services\DbProvisioner;
use PHPUnit\Framework\TestCase;

class DbProvisionerTest extends TestCase
{
    public function test_identifier_is_sanitised_and_bounded(): void
    {
        $this->assertSame('fz_deteksi_hoaks', DbProvisioner::ident('deteksi-hoaks'));
        $this->assertSame('fz_a_drop_database_x_b', DbProvisioner::ident("a'; DROP DATABASE x;--b"));
        $this->assertSame(32, strlen(DbProvisioner::ident(str_repeat('abc-', 30))));
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', DbProvisioner::ident('Ünï-Cödé 123'));
    }
}
