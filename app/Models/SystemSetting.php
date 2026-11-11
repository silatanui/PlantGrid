<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $fillable = ['setting_key', 'setting_value', 'description', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $value = static::query()->where('setting_key', $key)->value('setting_value');

        return $value === null ? $default : $value;
    }

    public static function putValue(string $key, ?string $value, ?int $userId = null, ?string $description = null): void
    {
        $setting = static::query()->firstOrNew(['setting_key' => $key]);
        $setting->setting_value = $value;
        if ($description !== null && $setting->description === null) {
            $setting->description = $description;
        }
        $setting->updated_by = $userId;
        $setting->save();
    }
}
