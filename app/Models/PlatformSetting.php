<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getValue(string $key, $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();
            if ($setting && $setting->value !== null && $setting->value !== '') {
                return $setting->value;
            }
        } catch (\Throwable $e) {
            // Fallback during early boot / before migration
        }

        return $default;
    }

    public static function setValue(string $key, $value): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value !== null ? (string) $value : null]
        );
    }

    public static function platformName(): string
    {
        return (string) static::getValue('platform_name', config('platform.name', 'KasirLoka'));
    }

    public static function platformTagline(): string
    {
        return (string) static::getValue('platform_tagline', config('platform.tagline', 'Aplikasi Kasir POS & Keuangan UMKM Anti-Offline'));
    }
}
