<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Degree extends Model
{
    use HasFactory;

    // --- لا تغيير هنا ---
    protected $fillable = [
        'degree_name', // تم تعديلها لتطابق اسم العمود في قاعدة البيانات
        'faculty_id',  // تم تعديلها لتطابق اسم العمود في قاعدة البيانات
    ];

    // --- تم تعديل أسماء الأعمدة هنا ---
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'degree_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'degree_courses', 'degree_id', 'course_id');
    }
}