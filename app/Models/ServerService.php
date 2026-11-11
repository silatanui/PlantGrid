<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerService extends Model
{
    protected $fillable = ['server_id', 'service_name', 'description', 'port', 'protocol', 'status'];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
