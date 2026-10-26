<?php

namespace App\Models;

use App\Enums\IncentiveTarget;
use Illuminate\Database\Eloquent\Model;

class IncentiveScheme extends Model
{
    protected $fillable = ['name', 'applies_to', 'rules', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['applies_to' => IncentiveTarget::class, 'rules' => 'array', 'is_active' => 'boolean'];
    }
}
