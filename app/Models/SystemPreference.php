<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemPreference extends Model
{
    use HasFactory;

    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = ['key', 'value', 'type', 'label', 'description', 'category', 'is_public'];

    protected function casts(): array
    {
        return [
            'is_public'  => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

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
