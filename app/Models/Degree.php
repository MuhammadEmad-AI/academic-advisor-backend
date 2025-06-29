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

    protected $fillable = [
        'DegreeName',
        'FacultyID',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'FacultyID');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'DegreeID');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'degree_courses', 'DegreeID', 'CourseID');
    }
}
