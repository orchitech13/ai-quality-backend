<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionReport extends Model
{
    protected $fillable = [
        'product_name',
        'defect_type',
        'description',
        'ai_result'
    ];
}
