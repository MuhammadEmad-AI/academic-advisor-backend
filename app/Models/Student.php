<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- إضافة بسيطة للـ use statement

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_name',
        'student_number',
        'image',
        'mobile',
        'gpa',
        'degree_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id'); // تم تحديد المفتاح الخارجي للتأكيد
    }

    public function degree(): BelongsTo
    {
        return $this->belongsTo(Degree::class, 'degree_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'student_courses', 'student_id', 'course_id')
            ->withPivot('grade', 'status', 'semester_id')
            ->withTimestamps();
    }

    public function studyPlans(): HasMany // تم إضافة Type Hint
    {
        return $this->hasMany(StudyPlan::class, 'student_id'); // تم تحديد المفتاح الخارجي للتأكيد
    }
}