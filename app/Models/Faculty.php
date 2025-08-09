<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasFactory;

    // --- تم تعديل أسماء الأعمدة هنا ---
    protected $fillable = [
        'faculty_name',
        'university_name',
    ];

    // --- تم تعديل المفتاح الخارجي هنا ---
    public function degrees(): HasMany
    {
        return $this->hasMany(Degree::class, 'faculty_id');
    }
}