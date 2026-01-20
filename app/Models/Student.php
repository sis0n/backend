<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'students';

    protected $primaryKey = 'student_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'student_number',
        'course_id',
        'year_level',
        'section',
        'contact',
        'status',
        'profile_updated',
        'can_edit_profile',
        'registration_form',
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
