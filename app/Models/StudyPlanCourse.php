<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- إضافة

class StudyPlanCourse extends Model
{
    use HasFactory;

    protected $fillable = [
        'study_plan_id',
        'course_id',
    ];

    public function studyPlan(): BelongsTo // تم إضافة Type Hint
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    public function course(): BelongsTo // تم إضافة Type Hint
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}