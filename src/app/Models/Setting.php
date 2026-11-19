<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;
    protected $guarded = [];

    private const SECRET = []; // tidak ada rahasia yang disimpan di pengaturan lagi (token Cloudflare hanya di .env)

    public static function get(string $key): mixed
    {
        $row = static::find($key);
        if ($row && $row->value !== null && $row->value !== '') {
            return in_array($key, self::SECRET) ? Crypt::decryptString($row->value) : $row->value;
        }
        return config("fasinova.$key");
    }

    public static function put(string $key, ?string $value): void
    {
        if ($value !== null && in_array($key, self::SECRET)) {
            $value = Crypt::encryptString($value);
        }
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
