<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; // <-- أضف هذا السطر

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_name',
        'course_number',
        'credit_hours',
        'description',
        'status',
    ];

    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'prerequisites', 'course_id', 'prerequisite_id');
    }

    public function isPrerequisiteFor(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'prerequisites', 'prerequisite_id', 'course_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_courses', 'course_id', 'student_id');
    }

    public function degrees(): BelongsToMany
    {
        return $this->belongsToMany(Degree::class, 'degree_courses', 'course_id', 'degree_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class, 'course_id');
    }

}