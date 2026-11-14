<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssetAttachment extends Model
{
    protected $fillable = [
        'asset_id', 'uploaded_by', 'kind', 'original_name', 'stored_name', 'storage_path', 'mime_type', 'file_size',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(png|jpe?g|gif|webp|bmp)$/i', (string) $this->original_name);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'photo' => 'Photo',
            'invoice' => 'Invoice',
            'document' => 'Document',
            default => Str::title((string) $this->kind),
        };
    }
}
