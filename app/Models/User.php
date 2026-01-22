<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    
    protected $table = 'users';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'username',
        'password',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'propfile_picture',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'deleted_at',
        'deleted_by',
        'is_archived',
    ];

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'user_id', 'user_id');
    }

    public function faculty(): HasOne
    {
        return $this->hasOne(Faculty::class, 'user_id', 'user_id');
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id', 'user_id');
    }
}
