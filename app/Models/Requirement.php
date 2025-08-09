<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use HasFactory;

    // --- تم تعديل أسماء الأعمدة هنا ---
    protected $fillable = [
        'degree_id',
        'course_id',
        'requirement_type',
    ];
}