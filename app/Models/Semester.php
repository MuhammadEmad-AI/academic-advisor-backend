<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Semester extends Model
{
    use HasFactory;

    // --- تم تعديل أسماء الأعمدة هنا ---
    protected $fillable = [
        'SemesterName',
        'Year',
    ];

    // --- تم تعديل المفاتيح الخارجية والوسيطة هنا ---
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_semesters', 'semester_id', 'course_id');
    }
}