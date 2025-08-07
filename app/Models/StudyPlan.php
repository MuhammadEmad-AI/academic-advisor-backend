<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'name',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function studyPlanCourses()
    {
        return $this->hasMany(StudyPlanCourse::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'study_plan_courses');
    }
}
