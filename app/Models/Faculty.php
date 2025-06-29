<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasFactory;

    protected $fillable = [
        'FacultyName',
        'UniversityName',
    ];

    public function degrees(): HasMany
    {
        return $this->hasMany(Degree::class, 'FacultyID');
    }
}
