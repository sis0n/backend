<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserProfileSeeder extends Seeder
{
    public function run(): void
    {
        $college = DB::table('colleges')->where('college_code', 'CLAS')->first();
        
        $collegeId = $college ? $college->college_id : 1;

        $facultyUserId = DB::table('users')->insertGetId([
            'username'   => 'faculty_maria',
            'password'   => Hash::make('password123'),
            'first_name' => 'Maria',
            'last_name'  => 'Santos',
            'email'      => 'faculty@example.com',
            'role'       => 'faculty',
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        DB::table('faculty')->insert([
            'user_id'           => $facultyUserId,
            'unique_faculty_id' => 'FAC-2026-002',
            'college_id'        => $collegeId,
            'contact'           => '0917-123-4567',
            'profile_updated'   => 1
        ]);

        $staffUserId = DB::table('users')->insertGetId([
            'username'   => 'staff_ricardo',
            'password'   => Hash::make('password123'),
            'first_name' => 'Ricardo',
            'last_name'  => 'Dalisay',
            'email'      => 'staff@example.com',
            'role'       => 'staff',
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        DB::table('staff')->insert([
            'user_id'        => $staffUserId,
            'employee_id'    => 'STAFF-2026-001',
            'position'       => 'Administrative Assistant',
            'contact'        => '0922-987-6543',
            'status'         => 'active',
            'profile_updated'=> 1
        ]);
    }
}