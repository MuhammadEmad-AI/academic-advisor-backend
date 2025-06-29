<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Course extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_name',
        'course_number',
        'credit_hours',
        'description',
        'status',
    ];

    /**
     * The prerequisites for this course.
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'prerequisites', 'CourseID', 'PrerequisiteID');
    }

    /**
     * The courses that this course is a prerequisite for.
     */
    public function isPrerequisiteFor(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'prerequisites', 'PrerequisiteID', 'CourseID');
    }

    /**
     * The students that have taken this course.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_courses', 'CourseID', 'StudentID');
    }

    /**
     * The degrees that require this course.
     */
    public function degrees(): BelongsToMany
    {
        return $this->belongsToMany(Degree::class, 'degree_courses', 'CourseID', 'DegreeID');
    }
}
