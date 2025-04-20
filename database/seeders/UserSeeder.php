<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('gtnh.admin_email', env('ADMIN_USER_EMAIL', 'admin@example.com'));
        $password = env('ADMIN_USER_PASSWORD');
        $name = config('gtnh.admin_name', env('ADMIN_USER_NAME', 'Admin User'));

        // Basic validation
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->command->error("Invalid or missing ADMIN_USER_EMAIL in environment.");
            Log::error("Admin user seeding failed: Invalid or missing ADMIN_USER_EMAIL.");
            return;
        }
        if (empty($password)) {
            $this->command->error("Missing ADMIN_USER_PASSWORD in environment. Cannot seed admin user.");
            Log::error("Admin user seeding failed: Missing ADMIN_USER_PASSWORD.");
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
            ]
        );

        $this->command->info("Admin user seeded/updated: {$email}");
        Log::info("Admin user seeded/updated successfully.", ['email' => $email]);
    }
}
