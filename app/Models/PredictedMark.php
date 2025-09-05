<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictedMark extends Model
{
    use HasFactory;

    /**
     * اسم الجدول في قاعدة البيانات المرتبط بهذا المودل.
     */
    protected $table = 'predicted_marks';

    /**
     * الأعمدة التي نسمح بتعبئتها بشكل جماعي (Mass Assignment).
     * يجب أن تطابق أسماء الأعمدة في قاعدة البيانات.
     */
    protected $fillable = [
        'student_number',
        'course_number',
        'predicted_mark',
    ];

    /**
     * تعريف علاقة "ينتمي إلى" مع مودل المادة (Course).
     * هذا يسمح لنا بالوصول لمعلومات المادة بسهولة من خلال التنبؤ.
     * مثال: $prediction->course->credit_hours
     */
    public function course()
    {
        // الربط يتم باستخدام عمود 'course_number' في كلا الجدولين
        return $this->belongsTo(Course::class, 'course_number', 'course_number');
    }

    /**
     * تعريف علاقة "ينتمي إلى" مع مودل الطالب (Student).
     * هذا يسمح لنا بالوصول لمعلومات الطالب بسهولة من خلال التنبؤ.
     * مثال: $prediction->student->student_name
     */
    public function student()
    {
        // الربط يتم باستخدام عمود 'student_number' في كلا الجدولين
        return $this->belongsTo(Student::class, 'student_number', 'student_number');
    }
}