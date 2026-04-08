<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'image',
        'slug',
        'status',
    ];

    const ALL_METHODS = [
        'knet' => 1,
        'apple-pay' => 6,
        'Google Pay' => 32,
        'Visa' => 2,
        'Stc Pay' => 14,
        'Benefit' => 5,
        //cash
        'cash' => 3,

    ];
}
