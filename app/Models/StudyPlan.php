<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- إضافة
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- إضافة
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // <-- إضافة

class StudyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'name',
    ];

    public function student(): BelongsTo // تم إضافة Type Hint
    {
        return $this->belongsTo(Student::class, 'student_id'); // تم تحديد المفتاح الخارجي للتأكيد
    }

    public function studyPlanCourses(): HasMany // تم إضافة Type Hint
    {
        return $this->hasMany(StudyPlanCourse::class, 'study_plan_id');
    }

    public function courses(): BelongsToMany // تم إضافة Type Hint
    {
        return $this->belongsToMany(Course::class, 'study_plan_courses', 'study_plan_id', 'course_id');
    }
}