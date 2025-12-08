<?php

namespace App\Models;

use App\Enums\SettingsKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting value by key.
     *
     * @param SettingsKey $key
     * @return mixed|null
     */
    public static function get(SettingsKey $key): mixed
    {
        $setting = static::where('key', $key->value)->first();

        return $setting?->value;
    }

    /**
     * Set a setting value by key.
     *
     * @param SettingsKey $key
     * @param mixed $value
     * @return void
     */
    public static function set(SettingsKey $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key->value],
            ['value' => $value]
        );
    }
}
