<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = ['name', 'contact_person', 'email', 'phone', 'address', 'support_notes'];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
