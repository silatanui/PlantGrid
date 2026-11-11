<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    private const SECRET_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'api_key',
    ];

    public function log(string $action, string $entityType, ?int $entityId = null, array $old = [], array $new = []): void
    {
        AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $this->clean($old),
            'new_values' => $this->clean($new),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ]);
    }

    private function clean(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        foreach (self::SECRET_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}
