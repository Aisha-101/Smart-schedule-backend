<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run()
    {

    /*
    |--------------------------------------------------------------------------
    | Specialists
    |--------------------------------------------------------------------------
    */

    DB::table('users')->insert([

    [
    'name'=>'John Specialist',
    'email'=>'specialist1@test.lt',
    'password'=>Hash::make('User123'),
    'role'=>'SPECIALIST'
    ],

    [
    'name'=>'Emma Specialist',
    'email'=>'specialist2@test.lt',
    'password'=>Hash::make('User123'),
    'role'=>'SPECIALIST'
    ],

    [
    'name'=>'Test Client',
    'email'=>'user@test.lt',
    'password'=>Hash::make('User123'),
    'role'=>'CLIENT'
    ]

    ]);


    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    DB::table('services')->insert([

[
'name' => 'Haircut',
'price' => 25,
'duration' => 30,
'specialist_id' => 1,
'created_at' => now(),
'updated_at' => now()
],

[
'name' => 'Coloring',
'price' => 60,
'duration' => 60,
'specialist_id' => 1,
'created_at' => now(),
'updated_at' => now()
],

[
'name' => 'Massage',
'price' => 50,
'duration' => 45,
'specialist_id' => 2,
'created_at' => now(),
'updated_at' => now()
],

]);


    /*
    |--------------------------------------------------------------------------
    | Specialist Availability
    |--------------------------------------------------------------------------
    */

    DB::table('specialist_availabilities')->insert([

    [
    'specialist_id'=>1,
    'date'=>'2026-05-01',
    'start_time'=>'09:00:00',
    'end_time'=>'17:00:00'
    ],

    [
    'specialist_id'=>2,
    'date'=>'2026-05-01',
    'start_time'=>'10:00:00',
    'end_time'=>'18:00:00'
    ],

    [
    'specialist_id'=>1,
    'date'=>'2026-05-02',
    'start_time'=>'09:00:00',
    'end_time'=>'17:00:00'
    ]

    ]);


    /*
    |--------------------------------------------------------------------------
    | Test Appointments
    |--------------------------------------------------------------------------
    */

    DB::table('appointments')->insert([

    [
    'client_id'=>3,
    'specialist_id'=>1,
    'start_time'=>'2026-05-01 10:00:00',
    'end_time'=>'2026-05-01 10:30:00',
    'status'=>'booked',
    'created_at'=>now(),
    'updated_at'=>now()
    ],

    [
    'client_id'=>3,
    'specialist_id'=>2,
    'start_time'=>'2026-05-02 14:00:00',
    'end_time'=>'2026-05-02 14:30:00',
    'status'=>'booked',
    'created_at'=>now(),
    'updated_at'=>now()
    ]

    ]);

    }
}