<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('DEFAULT_ADMIN_EMAIL');
        $password = env('DEFAULT_ADMIN_PASSWORD');

        // Validate .env input
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            dump('❌ Super Admin seeding failed (invalid .env data).');
            dump($validator->errors()->toArray());

            return;
        }

        // type is stored as 0 (not the string "super-admin")
        $existing = Admin::query()->where('type', Admin::SUPER_ADMIN)->first();

        if ($existing) {
            dump('ℹ️ Super Admin already exists → Seeder skipped.');

            return;
        }

        // Seed Super Admin (password auto-hashed by model mutator)
        Admin::create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => $password,
            'type' => Admin::SUPER_ADMIN,
        ]);

        dump('✅ Super Admin created successfully.');
    }
}
