<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    protected $fillable = [
        'name',
        'country_code',
        'state',
        'city',
        'status',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(UniversityDomain::class);
    }
}
