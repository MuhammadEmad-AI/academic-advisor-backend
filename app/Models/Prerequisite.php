<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prerequisite extends Model
{
    // إذا كان اسم الجدول فى قاعدة البيانات هو 'prerequisites'
    protected $table = 'prerequisites';

    // الأعمدة التى يمكن تعبئتها جماعيًا
    protected $fillable = [
        'course_id',
        'prerequisite_id',
    ];

    /**
     * المادة التى تتبع هذا السطر (المادة التى لها متطلبات).
     * مثال: المتطلب المسبق لمادة PH101 هو MA101،
     * إذن هنا course() ترجع مادة PH101.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * المادة التى تعتبر متطلبًا (المادة المسبقة نفسها).
     * مثال: prerequisite() ترجع مادة MA101 بالنسبة للسطر أعلاه.
     */
    public function prerequisite()
    {
        return $this->belongsTo(Course::class, 'prerequisite_id');
    }
}
