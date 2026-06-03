<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerSetting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['key', 'value', 'type', 'label', 'description', 'group'];

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => (bool)(int) $this->value,
            'integer' => (int) $this->value,
            default   => $this->value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::find($key);
            return $setting ? $setting->typedValue() : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => (string) $value]);
    }
}
