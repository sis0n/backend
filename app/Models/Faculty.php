<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    protected $table = 'faculty';

    protected $primaryKey = 'faculty_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'unique_faculty_id',
        'college_id',
        'contact',
        'status',
        'profile_updated',
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
