<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

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
            'email'    => $email,
            'password' => $password,
        ], [
            'email'    => 'required|email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            dump("❌ Super Admin seeding failed (invalid .env data).");
            dump($validator->errors()->toArray());
            return;
        }

        // Check if super admin already exists
        $existing = Admin::where('type', 'super-admin')->first();

        if ($existing) {
            dump("ℹ️ Super Admin already exists → Seeder skipped.");
            return;
        }

        // Seed Super Admin (password auto-hashed by model mutator)
        Admin::create([
            'name'     => 'Super Admin',
            'email'    => $email,
            'password' => $password,
            'type'     => '0',
        ]);

        dump("✅ Super Admin created successfully.");
    }
}
